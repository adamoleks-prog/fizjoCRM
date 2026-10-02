<?php

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Scopes\OperatorScope;
use App\Models\User;
use App\Services\CollisionChecker;
use App\Services\Messaging\AppSettings;
use App\Services\Messaging\PhoneNumber;
use App\Services\Messaging\ReminderMessage;
use App\Services\Messaging\SmsGateway;
use App\Services\SlotService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Patients booking themselves from the public page.
 *
 * The phone is confirmed with an SMS code before anything is booked. A patient
 * already on file (same phone and surname) gets the visit straight away; anyone
 * else gets a new record and a visit waiting for the physiotherapist's approval.
 */
class OnlineBooking
{
    private const CODE_TTL_MINUTES = 10;

    private const MAX_CODE_ATTEMPTS = 5;

    public const TEST_CODE = '123456';

    public const TEST_MODE_HOURS = 48;

    public function __construct(
        private readonly SlotService $slots,
        private readonly CollisionChecker $collisions,
        private readonly SmsGateway $sms,
        private readonly AppSettings $settings,
    ) {}

    /** When test mode ends, or null when it is off (or has run out). */
    public static function testModeUntil(AppSettings $settings): ?Carbon
    {
        $until = $settings->get('booking.test_until');

        return $until && Carbon::parse($until)->isFuture() ? Carbon::parse($until) : null;
    }

    /** No SMS gateway yet: codes are not sent and the fixed test code works. */
    public function isTestMode(): bool
    {
        return self::testModeUntil($this->settings) !== null;
    }

    /** @return Collection<int, User> */
    public function physiotherapists(): Collection
    {
        return User::where('online_booking_enabled', true)->orderBy('name')->get();
    }

    public function isOpen(User $physiotherapist): bool
    {
        return $physiotherapist->online_booking_enabled && ($this->sms->isConfigured() || $this->isTestMode());
    }

    public function visitMinutes(User $physiotherapist, bool $firstVisit): int
    {
        $slot = $this->slots->slotMinutes($physiotherapist->id);
        $minutes = $firstVisit
            ? ($physiotherapist->booking_first_visit_minutes ?: $physiotherapist->booking_visit_minutes)
            : $physiotherapist->booking_visit_minutes;

        return max($slot, (int) ($minutes ?: $slot));
    }

    /**
     * Start times on the day where a visit of the given length fits, respecting
     * the minimum notice and how far ahead booking is open.
     *
     * @return Collection<int, Carbon>
     */
    public function freeStarts(User $physiotherapist, CarbonInterface $date, int $minutes): Collection
    {
        $day = Carbon::parse($date)->startOfDay();

        if ($day->gt($this->lastBookableDay($physiotherapist))) {
            return collect();
        }

        $earliest = now()->addHours($physiotherapist->booking_min_notice_hours);
        $needed = (int) ceil($minutes / $this->slots->slotMinutes($physiotherapist->id));

        return $this->slots->availableSlots($physiotherapist->id, $day)
            ->map(fn (array $slot) => $slot['starts_at'])
            ->filter(fn (Carbon $start) => $start->gte($earliest)
                && $this->slots->consecutiveFreeSlots($physiotherapist->id, $start) >= $needed)
            ->values();
    }

    /**
     * Days with at least one free start, for the date picker.
     *
     * @return Collection<int, Carbon>
     */
    public function freeDays(User $physiotherapist, int $minutes): Collection
    {
        $days = collect();
        $day = today();
        $last = $this->lastBookableDay($physiotherapist);

        while ($day->lte($last)) {
            if ($this->freeStarts($physiotherapist, $day, $minutes)->isNotEmpty()) {
                $days->push($day->copy());
            }

            $day = $day->addDay();
        }

        return $days;
    }

    /**
     * Checks the request and sends the code. Returns the verification id.
     *
     * @param  array{starts_at: string, first_visit: bool, first_name: string, last_name: string, email: ?string, phone: string}  $data
     */
    public function requestCode(User $physiotherapist, array $data, ?string $ip): int
    {
        $phone = PhoneNumber::normalize($data['phone']);

        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => 'Podaj poprawny numer telefonu komórkowego.']);
        }

        $minutes = $this->visitMinutes($physiotherapist, $data['first_visit']);
        $start = Carbon::parse($data['starts_at']);
        $this->ensureStillFree($physiotherapist, $start, $minutes);

        if ($this->hasOpenOnlineBooking($physiotherapist, $phone)) {
            throw ValidationException::withMessages([
                'phone' => 'Na ten numer jest już umówiona wizyta przez internet. Aby umówić kolejną, zadzwoń do gabinetu.',
            ]);
        }

        foreach (['phone:'.$phone => 3, 'ip:'.$ip => 10] as $key => $limit) {
            if (RateLimiter::tooManyAttempts('booking-code:'.$key, $limit)) {
                throw ValidationException::withMessages(['phone' => 'Zbyt wiele prób. Spróbuj ponownie za godzinę albo zadzwoń do gabinetu.']);
            }
        }

        $code = $this->isTestMode() ? self::TEST_CODE : (string) random_int(100000, 999999);

        $id = DB::table('booking_verifications')->insertGetId([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'payload' => json_encode([
                'operator_id' => $physiotherapist->id,
                'starts_at' => $start->format('Y-m-d H:i:s'),
                'minutes' => $minutes,
                'first_visit' => $data['first_visit'],
                'first_name' => trim($data['first_name']),
                'last_name' => trim($data['last_name']),
                'email' => $data['email'] ?: null,
            ]),
            'ip_address' => $ip,
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            if (! $this->isTestMode()) {
                $this->sms->send($phone, "Kod zapisu na wizyte: {$code}. Wazny ".self::CODE_TTL_MINUTES.' minut. Nie podawaj go nikomu.');
            }
        } catch (Throwable) {
            DB::table('booking_verifications')->where('id', $id)->delete();

            throw ValidationException::withMessages(['phone' => 'Nie udało się wysłać SMS-a z kodem. Spróbuj ponownie lub zadzwoń do gabinetu.']);
        }

        RateLimiter::hit('booking-code:phone:'.$phone, 3600);
        RateLimiter::hit('booking-code:ip:'.$ip, 3600);

        return $id;
    }

    /**
     * Checks the code and books the visit.
     */
    public function confirm(int $verificationId, string $code): Appointment
    {
        $verification = DB::table('booking_verifications')->where('id', $verificationId)->first();

        if (! $verification || $verification->used_at || Carbon::parse($verification->expires_at)->isPast()
            || $verification->attempts >= self::MAX_CODE_ATTEMPTS) {
            throw ValidationException::withMessages(['code' => 'Kod wygasł. Wybierz termin jeszcze raz.']);
        }

        if (! Hash::check(trim($code), $verification->code_hash)) {
            DB::table('booking_verifications')->where('id', $verificationId)->increment('attempts');
            $left = self::MAX_CODE_ATTEMPTS - $verification->attempts - 1;

            throw ValidationException::withMessages(['code' => $left > 0 ? "Nieprawidłowy kod. Pozostało prób: {$left}." : 'Nieprawidłowy kod. Wybierz termin jeszcze raz.']);
        }

        $payload = json_decode($verification->payload, true);
        $physiotherapist = User::findOrFail($payload['operator_id']);
        $start = Carbon::parse($payload['starts_at']);
        $end = $start->copy()->addMinutes($payload['minutes']);

        $appointment = DB::transaction(function () use ($verificationId, $verification, $payload, $physiotherapist, $start, $end) {
            DB::table('booking_verifications')->where('id', $verificationId)->update(['used_at' => now()]);

            if ($this->collisions->hasCollision($physiotherapist->id, $start, $end, lock: true)) {
                throw ValidationException::withMessages(['code' => 'Ktoś właśnie zajął ten termin. Wybierz inny.']);
            }

            $patient = $this->findPatient($physiotherapist, $verification->phone, $payload['last_name']);
            $known = $patient !== null;

            if (! $patient) {
                $patient = new Patient([
                    'first_name' => $payload['first_name'],
                    'last_name' => $payload['last_name'],
                    'phone' => $verification->phone,
                    'email' => $payload['email'],
                    'reminders_enabled' => true,
                ]);
                $patient->operator_id = $physiotherapist->id;
                $patient->source = 'online';
                $patient->online_consent_at = now();
                $patient->save();
            }

            $appointment = new Appointment([
                'patient_id' => $patient->id,
                'starts_at' => $start,
                'ends_at' => $end,
                'status' => $known ? AppointmentStatus::Scheduled : AppointmentStatus::Pending,
            ]);
            $appointment->operator_id = $physiotherapist->id;
            $appointment->source = 'online';
            $appointment->cancel_token = Str::random(40);
            $appointment->save();

            return $appointment;
        });

        $this->notify($appointment, $physiotherapist);

        return $appointment;
    }

    public function approve(Appointment $appointment): void
    {
        $appointment->update(['status' => AppointmentStatus::Scheduled]);
        $this->notify($appointment, User::findOrFail($appointment->operator_id));
    }

    public function reject(Appointment $appointment): void
    {
        $appointment->update(['status' => AppointmentStatus::Cancelled]);
        $physiotherapist = User::findOrFail($appointment->operator_id);

        $this->text($appointment, 'Niestety nie mozemy przyjac wizyty '.ReminderMessage::when($appointment)
            .($physiotherapist->practice_phone ? '. Prosimy o kontakt: '.$physiotherapist->practice_phone : '').'.');
    }

    /** Whether the patient may still cancel by themselves. */
    public function canCancel(Appointment $appointment): bool
    {
        $hours = User::find($appointment->operator_id)?->booking_cancel_hours ?? 24;

        return in_array($appointment->status, [AppointmentStatus::Scheduled, AppointmentStatus::Pending], true)
            && $appointment->starts_at->gt(now()->addHours($hours));
    }

    public function cancel(Appointment $appointment): void
    {
        $appointment->update(['status' => AppointmentStatus::Cancelled]);
    }

    public function cancelUrl(Appointment $appointment): string
    {
        return route('booking.cancel', $appointment->cancel_token);
    }

    private function notify(Appointment $appointment, User $physiotherapist): void
    {
        $where = $physiotherapist->practice_name ? ' w '.$physiotherapist->practice_name : '';
        $when = ReminderMessage::when($appointment);

        $this->text($appointment, $appointment->status === AppointmentStatus::Pending
            ? "Otrzymalismy prosbe o wizyte{$where} {$when}. Potwierdzimy ja SMS-em."
            : "Potwierdzamy wizyte{$where} {$when}. Odwolanie: ".$this->cancelUrl($appointment));
    }

    /** Best effort — the booking stands even if the confirmation SMS fails. */
    private function text(Appointment $appointment, string $message): void
    {
        $phone = Patient::withoutGlobalScope(OperatorScope::class)->whereKey($appointment->patient_id)->value('phone');

        try {
            if ($phone) {
                $this->sms->send($phone, $message);
            }
        } catch (Throwable) {
            //
        }
    }

    private function ensureStillFree(User $physiotherapist, Carbon $start, int $minutes): void
    {
        $free = $this->freeStarts($physiotherapist, $start, $minutes)->contains(fn (Carbon $s) => $s->equalTo($start));

        if (! $free) {
            throw ValidationException::withMessages(['starts_at' => 'Ten termin nie jest już dostępny. Wybierz inny.']);
        }
    }

    /**
     * Same phone and surname on this physiotherapist's list. A phone alone is not
     * enough: family members often share one, and the visit must land on the
     * right card.
     */
    private function findPatient(User $physiotherapist, string $phone, string $lastName): ?Patient
    {
        return $this->patientsWithPhone($physiotherapist, $phone)
            ->first(fn (Patient $p) => mb_strtolower(trim($p->last_name)) === mb_strtolower(trim($lastName)));
    }

    private function hasOpenOnlineBooking(User $physiotherapist, string $phone): bool
    {
        $ids = $this->patientsWithPhone($physiotherapist, $phone)->pluck('id');

        return $ids->isNotEmpty() && Appointment::withoutGlobalScopes()
            ->whereIn('patient_id', $ids)
            ->where('source', 'online')
            ->whereIn('status', [AppointmentStatus::Scheduled, AppointmentStatus::Pending])
            ->where('starts_at', '>', now())
            ->exists();
    }

    /** @return Collection<int, Patient> */
    private function patientsWithPhone(User $physiotherapist, string $phone): Collection
    {
        return Patient::withoutGlobalScope(OperatorScope::class)
            ->where('operator_id', $physiotherapist->id)
            ->whereNotNull('phone')
            ->get(['id', 'phone', 'first_name', 'last_name'])
            ->filter(fn (Patient $p) => PhoneNumber::normalize($p->phone) === $phone)
            ->values();
    }

    private function lastBookableDay(User $physiotherapist): Carbon
    {
        return today()->addDays($physiotherapist->booking_days_ahead);
    }
}
