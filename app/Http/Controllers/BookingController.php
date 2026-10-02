<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\ConsentTemplate;
use App\Models\User;
use App\Services\Booking\OnlineBooking;
use App\Services\Booking\Recaptcha;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The public booking pages — no login. Every step re-checks the slot, and the
 * visit is only created once the phone is confirmed with the SMS code.
 */
class BookingController extends Controller
{
    private const SESSION_KEY = 'booking.verification';

    public function __construct(
        private readonly OnlineBooking $booking,
        private readonly Recaptcha $recaptcha,
    ) {}

    public function index(): View|RedirectResponse
    {
        $physiotherapists = $this->booking->physiotherapists()->filter(fn (User $u) => $this->booking->isOpen($u))->values();

        if ($physiotherapists->count() === 1) {
            return redirect()->route('booking.show', $physiotherapists->first());
        }

        return view('booking.index', ['physiotherapists' => $physiotherapists]);
    }

    public function show(Request $request, User $physiotherapist): View
    {
        abort_unless($this->booking->isOpen($physiotherapist), 404);

        $types = $this->booking->visitTypes();
        $type = array_key_exists((string) $request->query('typ'), $types) ? $request->query('typ') : null;
        $firstVisit = $type ? $types[$type]['first'] : false;
        $minutes = $type ? $this->booking->minutesFor($physiotherapist, $types[$type]['service'], $firstVisit) : null;

        $days = $minutes ? $this->booking->freeDays($physiotherapist, $minutes) : collect();
        $date = $request->filled('data') ? Carbon::parse($request->query('data'))->startOfDay() : $days->first();
        $starts = $minutes && $date ? $this->booking->freeStarts($physiotherapist, $date, $minutes) : collect();
        $chosen = $request->filled('godzina') ? $starts->first(fn ($s) => $s->format('H:i') === $request->query('godzina')) : null;

        return view('booking.show', [
            'physiotherapist' => $physiotherapist,
            'types' => $types,
            'type' => $type,
            'firstVisit' => $firstVisit,
            'minutes' => $minutes,
            'days' => $days,
            'date' => $date,
            'starts' => $starts,
            'chosen' => $chosen,
            'privacyText' => $this->privacyText($physiotherapist),
            'requiresSmsCode' => $this->booking->requiresSmsCode(),
            'recaptchaSiteKey' => $this->recaptcha->isActive() ? $this->recaptcha->siteKey() : null,
        ]);
    }

    public function requestCode(Request $request, User $physiotherapist): RedirectResponse
    {
        abort_unless($this->booking->isOpen($physiotherapist), 404);

        // Bots fill every field, people never see this one.
        if (filled($request->input('website'))) {
            abort(422);
        }

        $data = $request->validate([
            'typ' => ['required', 'string', Rule::in(array_keys($this->booking->visitTypes()))],
            'starts_at' => ['required', 'date_format:Y-m-d H:i'],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'consent' => ['accepted'],
        ], [
            'consent.accepted' => 'Zaznacz, że zapoznałeś(-aś) się z informacją o przetwarzaniu danych.',
        ]);

        if ($this->recaptcha->isActive() && ! $this->recaptcha->passes($request->input('recaptcha_token'), $request->ip())) {
            return back()->withInput()->withErrors([
                'recaptcha' => 'Nie udało się potwierdzić, że formularz wysyła człowiek. Odśwież stronę i spróbuj ponownie albo zadzwoń do gabinetu.',
            ]);
        }

        $type = $this->booking->visitTypes()[$data['typ']];

        $id = $this->booking->requestCode($physiotherapist, [
            'starts_at' => $data['starts_at'],
            'service' => $type['service'],
            'first_visit' => $type['first'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'],
        ], $request->ip());

        if (! $this->booking->requiresSmsCode()) {
            $appointment = $this->booking->bookWithoutCode($id);
            $request->session()->put('booking.done', $appointment->cancel_token);

            return redirect()->route('booking.done');
        }

        $request->session()->put(self::SESSION_KEY, $id);

        return redirect()->route('booking.code');
    }

    public function code(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has(self::SESSION_KEY)) {
            return redirect()->route('booking.index');
        }

        return view('booking.code');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $id = $request->session()->get(self::SESSION_KEY);

        if (! $id) {
            return redirect()->route('booking.index');
        }

        $request->validate(['code' => ['required', 'string', 'max:10']]);

        $appointment = $this->booking->confirm((int) $id, $request->string('code')->value());

        $request->session()->forget(self::SESSION_KEY);
        $request->session()->put('booking.done', $appointment->cancel_token);

        return redirect()->route('booking.done');
    }

    public function done(Request $request): View|RedirectResponse
    {
        $appointment = $this->byToken($request->session()->get('booking.done'));

        if (! $appointment) {
            return redirect()->route('booking.index');
        }

        return view('booking.done', [
            'appointment' => $appointment,
            'physiotherapist' => User::findOrFail($appointment->operator_id),
        ]);
    }

    public function cancelForm(string $token): View
    {
        $appointment = $this->byToken($token) ?? abort(404);

        return view('booking.cancel', [
            'appointment' => $appointment,
            'physiotherapist' => User::findOrFail($appointment->operator_id),
            'canCancel' => $this->booking->canCancel($appointment),
            'token' => $token,
        ]);
    }

    public function cancel(string $token): RedirectResponse
    {
        $appointment = $this->byToken($token) ?? abort(404);

        if ($this->booking->canCancel($appointment)) {
            $this->booking->cancel($appointment);
        }

        return redirect()->route('booking.cancel', $token);
    }

    private function byToken(?string $token): ?Appointment
    {
        return $token ? Appointment::withoutGlobalScopes()->where('cancel_token', $token)->first() : null;
    }

    /** The practice's own RODO clause, when the physiotherapist has one. */
    private function privacyText(User $physiotherapist): ?string
    {
        $template = ConsentTemplate::withoutGlobalScopes()
            ->where('operator_id', $physiotherapist->id)
            ->whereNull('deleted_at')
            ->where('name', 'like', '%RODO%')
            ->first();

        return $template ? strtr($template->body, [
            '{PACJENT}' => '…',
            '{DATA_URODZENIA}' => '…',
            '{GABINET}' => $physiotherapist->practice_name ?: $physiotherapist->name,
            '{FIZJOTERAPEUTA}' => $physiotherapist->name,
            '{DATA}' => now()->format('d.m.Y'),
        ]) : null;
    }
}
