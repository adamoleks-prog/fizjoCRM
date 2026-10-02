<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Services\Booking\OnlineBooking;
use App\Services\Messaging\AppSettings;
use Carbon\Carbon;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/** The code from the last SMS sent to SMSAPI. */
function lastSmsCode(): ?string
{
    $messages = collect(Http::recorded())->map(fn ($pair) => $pair[0]['message'] ?? '');
    preg_match('/(\d{6})/', (string) $messages->last(fn ($m) => str_contains($m, 'Kod')), $match);

    return $match[1] ?? null;
}

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-05 08:00')); // Monday

    Http::preventStrayRequests();
    Http::fake(['api.smsapi.pl/*' => Http::response(['count' => 1, 'list' => [['id' => '1', 'status' => 'QUEUE']]])]);

    app(AppSettings::class)->put(['sms.token' => 'test-token']);

    $this->physio = User::factory()->operator()->create([
        'name' => 'Izabela Testowa',
        'practice_name' => 'FizjoRoom',
        'practice_phone' => '500 100 200',
        'online_booking_enabled' => true,
        'booking_visit_minutes' => 30,
        'booking_first_visit_minutes' => 60,
        'booking_min_notice_hours' => 12,
        'booking_days_ahead' => 30,
        'booking_cancel_hours' => 24,
    ]);

    $this->existing = Patient::factory()->forOperator($this->physio)->create([
        'first_name' => 'Jan', 'last_name' => 'Kowalski', 'phone' => '602 118 940',
    ]);

    $this->book = function (array $overrides = []) {
        return $this->post(route('booking.request', $this->physio), array_merge([
            'typ' => 'kolejna',
            'starts_at' => '2026-10-06 10:00',
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'phone' => '602118940',
            'consent' => 1,
        ], $overrides));
    };
});

it('shows free days and slots, respecting the minimum notice', function () {
    // Today everything is closer than the 12-hour notice.
    $this->get(route('booking.show', [$this->physio, 'typ' => 'kolejna', 'data' => '2026-10-05']))
        ->assertOk()
        ->assertSee('Izabela Testowa')
        ->assertSee('Ten dzień jest już zajęty');

    $this->get(route('booking.show', [$this->physio, 'typ' => 'kolejna', 'data' => '2026-10-06']))
        ->assertSee('godzina=08%3A00', false)
        ->assertSee('godzina=17%3A30', false);
});

it('books an existing patient straight away after the SMS code', function () {
    ($this->book)()->assertRedirect(route('booking.code'));

    $code = lastSmsCode();
    expect($code)->not->toBeNull();

    $this->post(route('booking.confirm'), ['code' => $code])->assertRedirect(route('booking.done'));

    $appointment = Appointment::withoutGlobalScopes()->sole();

    expect($appointment->patient_id)->toBe($this->existing->id)
        ->and($appointment->status)->toBe(AppointmentStatus::Scheduled)
        ->and($appointment->source)->toBe('online')
        ->and($appointment->ends_at->format('H:i'))->toBe('10:30');

    Http::assertSent(fn (Request $r) => str_contains($r['message'] ?? '', 'Potwierdzamy wizyte w FizjoRoom')
        && str_contains($r['message'], route('booking.cancel', $appointment->cancel_token)));

    $this->get(route('booking.done'))->assertOk()->assertSee('Wizyta umówiona');
});

it('creates a new patient and waits for approval on a first visit', function () {
    ($this->book)(['typ' => 'pierwsza', 'first_name' => 'Ewa', 'last_name' => 'Nowa', 'phone' => '511222333', 'email' => 'ewa@example.com']);
    $this->post(route('booking.confirm'), ['code' => lastSmsCode()]);

    $appointment = Appointment::withoutGlobalScopes()->sole();
    $patient = Patient::withoutGlobalScopes()->find($appointment->patient_id);

    expect($appointment->status)->toBe(AppointmentStatus::Pending)
        ->and($appointment->ends_at->format('H:i'))->toBe('11:00')
        ->and($patient->last_name)->toBe('Nowa')
        ->and($patient->source)->toBe('online')
        ->and($patient->operator_id)->toBe($this->physio->id)
        ->and($patient->online_consent_at)->not->toBeNull();

    // The slot is held while waiting.
    $this->get(route('booking.show', [$this->physio, 'typ' => 'kolejna', 'data' => '2026-10-06']))->assertDontSee('>10:30<', false);

    $this->actingAs($this->physio)->post(route('booking.approve', $appointment))->assertSessionHasNoErrors();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Scheduled);
    Http::assertSent(fn (Request $r) => str_contains($r['message'] ?? '', 'Potwierdzamy wizyte'));
});

it('treats the same phone with another surname as a new patient', function () {
    ($this->book)(['first_name' => 'Anna', 'last_name' => 'Kowalska']);
    $this->post(route('booking.confirm'), ['code' => lastSmsCode()]);

    $appointment = Appointment::withoutGlobalScopes()->sole();

    expect($appointment->patient_id)->not->toBe($this->existing->id)
        ->and($appointment->status)->toBe(AppointmentStatus::Pending);
});

it('rejects a wrong code and locks after five attempts', function () {
    ($this->book)();

    foreach (range(1, 5) as $i) {
        $this->post(route('booking.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
    }

    $this->post(route('booking.confirm'), ['code' => lastSmsCode()])->assertSessionHasErrors('code');

    expect(Appointment::withoutGlobalScopes()->count())->toBe(0);
});

it('refuses a slot that is taken or outside the rules', function () {
    Appointment::factory()->forOperator($this->physio)->create([
        'patient_id' => $this->existing->id,
        'starts_at' => '2026-10-06 10:00', 'ends_at' => '2026-10-06 10:30',
    ]);

    ($this->book)()->assertSessionHasErrors('starts_at');
    ($this->book)(['starts_at' => '2026-10-05 09:00'])->assertSessionHasErrors('starts_at');   // too soon
    ($this->book)(['starts_at' => '2026-12-01 10:00'])->assertSessionHasErrors('starts_at');   // too far ahead

    Http::assertNothingSent();
});

it('allows one open online booking per phone', function () {
    ($this->book)();
    $this->post(route('booking.confirm'), ['code' => lastSmsCode()]);

    ($this->book)(['starts_at' => '2026-10-07 10:00'])->assertSessionHasErrors('phone');
});

it('lets the patient cancel from the link until the deadline', function () {
    ($this->book)(['starts_at' => '2026-10-08 10:00']);
    $this->post(route('booking.confirm'), ['code' => lastSmsCode()]);
    $appointment = Appointment::withoutGlobalScopes()->sole();

    $this->get(route('booking.cancel', $appointment->cancel_token))->assertOk()->assertSee('Odwołuję wizytę');
    $this->post(route('booking.cancel.confirm', $appointment->cancel_token))->assertRedirect();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled);
});

it('does not cancel within the deadline', function () {
    ($this->book)(['starts_at' => '2026-10-06 10:00']);
    $this->post(route('booking.confirm'), ['code' => lastSmsCode()]);
    $appointment = Appointment::withoutGlobalScopes()->sole();

    $this->travelTo(Carbon::parse('2026-10-05 20:00'));

    $this->get(route('booking.cancel', $appointment->cancel_token))->assertSee('nie można już odwołać');
    $this->post(route('booking.cancel.confirm', $appointment->cancel_token));

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Scheduled);
});

it('is closed when booking is off or SMS is not configured', function () {
    $this->physio->forceFill(['online_booking_enabled' => false])->save();
    $this->get(route('booking.show', $this->physio))->assertNotFound();
    $this->get(route('booking.index'))->assertOk()->assertSee('chwilowo niedostępne');
});

it('ignores bots filling the hidden field', function () {
    ($this->book)(['website' => 'http://spam'])->assertStatus(422);
    Http::assertNothingSent();
});

it('lets the physiotherapist set up booking and see pending visits', function () {
    $this->actingAs($this->physio)
        ->put(route('booking.settings.update'), [
            'online_booking_enabled' => 1,
            'booking_visit_minutes' => 45,
            'booking_min_notice_hours' => 6,
            'booking_days_ahead' => 14,
            'booking_cancel_hours' => 12,
        ])
        ->assertSessionHasErrors('booking_visit_minutes');   // not a multiple of 30

    $this->actingAs($this->physio)
        ->put(route('booking.settings.update'), [
            'online_booking_enabled' => 1,
            'booking_visit_minutes' => 60,
            'booking_min_notice_hours' => 6,
            'booking_days_ahead' => 14,
            'booking_cancel_hours' => 12,
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->physio)->get(route('booking.settings'))->assertOk()->assertSee(route('booking.show', $this->physio));
});

it('keeps approval to the physiotherapist who owns the visit', function () {
    ($this->book)(['typ' => 'pierwsza', 'last_name' => 'Nowa', 'phone' => '511222333']);
    $this->post(route('booking.confirm'), ['code' => lastSmsCode()]);
    $appointment = Appointment::withoutGlobalScopes()->sole();

    $this->actingAs(User::factory()->operator()->create())
        ->post(route('booking.approve', $appointment))
        ->assertNotFound();
});

/* ---------- test mode ---------- */

it('accepts the fixed code without sending SMS in test mode', function () {
    app(AppSettings::class)->put(['sms.token' => null]);
    Http::fake();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->put(route('admin.messaging.update'), [
        'mail_encryption' => 'tls',
        'reminders_hours_before' => 24,
        'booking_test_mode' => 1,
    ])->assertSessionHasNoErrors();

    auth()->logout();

    $this->get(route('booking.show', [$this->physio, 'typ' => 'kolejna']))
        ->assertOk()
        ->assertSee('TRYB TESTOWY');

    ($this->book)()->assertRedirect(route('booking.code'));
    $this->post(route('booking.confirm'), ['code' => '123456'])->assertRedirect(route('booking.done'));

    expect(Appointment::withoutGlobalScopes()->sole()->patient_id)->toBe($this->existing->id);
    Http::assertNothingSent();
});

it('switches test mode off by itself after 48 hours', function () {
    app(AppSettings::class)->put(['sms.token' => null]);

    $this->actingAs(User::factory()->admin()->create())->put(route('admin.messaging.update'), [
        'mail_encryption' => 'tls', 'reminders_hours_before' => 24, 'booking_test_mode' => 1,
    ]);
    auth()->logout();

    $this->get(route('booking.show', $this->physio))->assertOk();

    $this->travel(49)->hours();
    app()->forgetScopedInstances();

    $this->get(route('booking.show', $this->physio))->assertNotFound();
});

it('does not accept the test code when test mode is off', function () {
    ($this->book)();

    $this->post(route('booking.confirm'), ['code' => '123456'])->assertSessionHasErrors('code');
});

/* ---------- without SMS code, reCAPTCHA ---------- */

function adminSaves(array $fields): void
{
    test()->actingAs(User::factory()->admin()->create())
        ->put(route('admin.messaging.update'), array_merge(['mail_encryption' => 'tls', 'reminders_hours_before' => 24, 'booking_sms_verification' => 1], $fields))
        ->assertSessionHasNoErrors();
    auth()->logout();
}

it('books straight away when the SMS code is switched off', function () {
    app(AppSettings::class)->put(['sms.token' => null]);
    adminSaves(['booking_sms_verification' => 0]);
    Http::fake();

    $this->get(route('booking.show', [$this->physio, 'typ' => 'kolejna', 'data' => '2026-10-06', 'godzina' => '10:00']))
        ->assertOk()
        ->assertSee('Zapisz się')
        ->assertDontSee('Wyślij kod SMS');

    ($this->book)()->assertRedirect(route('booking.done'));

    expect(Appointment::withoutGlobalScopes()->sole())
        ->patient_id->toBe($this->existing->id)
        ->status->toBe(AppointmentStatus::Scheduled);

    // A new person still waits for the physiotherapist.
    ($this->book)(['typ' => 'pierwsza', 'starts_at' => '2026-10-07 10:00', 'last_name' => 'Nowa', 'phone' => '511222333'])
        ->assertRedirect(route('booking.done'));
    expect(Appointment::withoutGlobalScopes()->latest('id')->first()->status)->toBe(AppointmentStatus::Pending);

    Http::assertNothingSent();
});

it('cannot skip the code while it is required', function () {
    ($this->book)();
    $id = session('booking.verification');

    expect(fn () => app(OnlineBooking::class)->confirm($id, null))
        ->toThrow(ValidationException::class);
    expect(Appointment::withoutGlobalScopes()->count())->toBe(0);
});

it('checks reCAPTCHA when it is on', function () {
    adminSaves(['recaptcha_enabled' => 1, 'recaptcha_site_key' => 'site-key-123', 'recaptcha_secret_key' => 'secret-456']);

    $this->get(route('booking.show', [$this->physio, 'typ' => 'kolejna', 'data' => '2026-10-06', 'godzina' => '10:00']))
        ->assertSee('recaptcha/api.js?render=site-key-123', false);

    Http::fake([
        'www.google.com/recaptcha/*' => Http::sequence()
            ->push(['success' => true, 'action' => 'booking', 'score' => 0.1])
            ->push(['success' => true, 'action' => 'booking', 'score' => 0.9]),
        'api.smsapi.pl/*' => Http::response(['count' => 1]),
    ]);

    ($this->book)(['recaptcha_token' => 'bot'])->assertSessionHasErrors('recaptcha');
    ($this->book)(['recaptcha_token' => 'human'])->assertRedirect(route('booking.code'));

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'siteverify') && $r['secret'] === 'secret-456' && $r['response'] === 'human');
});

it('refuses when Google cannot be reached or no token is sent', function () {
    adminSaves(['recaptcha_enabled' => 1, 'recaptcha_site_key' => 'k', 'recaptcha_secret_key' => 's']);
    Http::fake(['www.google.com/recaptcha/*' => Http::response(null, 500)]);

    ($this->book)()->assertSessionHasErrors('recaptcha');
    ($this->book)(['recaptcha_token' => 'x'])->assertSessionHasErrors('recaptcha');
});

it('stores the reCAPTCHA secret encrypted and never shows it', function () {
    adminSaves(['recaptcha_enabled' => 1, 'recaptcha_site_key' => 'k', 'recaptcha_secret_key' => 'very-secret-value']);

    expect(DB::table('settings')->where('key', 'recaptcha.secret_key')->value('value'))->not->toBe('very-secret-value');

    $this->actingAs(User::factory()->admin()->create())->get(route('admin.messaging.edit'))
        ->assertDontSee('very-secret-value')
        ->assertSee('zapisany');
});

/* ---------- matching existing patients, field validation ---------- */

it('recognises a patient despite typos and missing Polish characters', function () {
    $this->existing->update(['first_name' => 'Łukasz', 'last_name' => 'Wójcik']);

    ($this->book)(['first_name' => 'lukasz', 'last_name' => 'Wojcki']);   // no diacritics + swapped letters
    $this->post(route('booking.confirm'), ['code' => lastSmsCode()]);

    expect(Appointment::withoutGlobalScopes()->sole())
        ->patient_id->toBe($this->existing->id)
        ->status->toBe(AppointmentStatus::Scheduled);
});

it('attaches a booking from a new number to the existing card, waiting for approval', function () {
    ($this->book)(['phone' => '699888777']);
    $this->post(route('booking.confirm'), ['code' => lastSmsCode()]);

    $appointment = Appointment::withoutGlobalScopes()->sole();

    expect($appointment->patient_id)->toBe($this->existing->id)
        ->and($appointment->status)->toBe(AppointmentStatus::Pending)
        ->and($appointment->booking_phone)->toBe('48699888777')
        ->and(Patient::withoutGlobalScopes()->count())->toBe(1);   // no duplicate card

    expect($appointment->load('patient')->bookedFromOtherPhone())->toBeTrue();

    // Texts about this booking go to the number given when booking.
    Http::assertSent(fn (Request $r) => ($r['to'] ?? null) === '48699888777' && str_contains($r['message'] ?? '', 'Otrzymalismy prosbe'));

    $this->actingAs($this->physio)->get(route('appointments.show', $appointment))->assertSee('z innego numeru');
});

it('does not guess between two patients with the same name and another number', function () {
    Patient::factory()->forOperator($this->physio)->create(['first_name' => 'Jan', 'last_name' => 'Kowalski', 'phone' => '501501501']);

    ($this->book)(['phone' => '699888777']);
    $this->post(route('booking.confirm'), ['code' => lastSmsCode()]);

    $appointment = Appointment::withoutGlobalScopes()->sole();

    expect($appointment->status)->toBe(AppointmentStatus::Pending)
        ->and(Patient::withoutGlobalScopes()->count())->toBe(3);   // a new card to check
});

it('does not merge clearly different names', function () {
    ($this->book)(['first_name' => 'Janina', 'last_name' => 'Kowalczyk', 'phone' => '699888777']);
    $this->post(route('booking.confirm'), ['code' => lastSmsCode()]);

    expect(Appointment::withoutGlobalScopes()->sole()->patient_id)->not->toBe($this->existing->id);
});

it('validates names and the phone number', function (array $fields, string $error) {
    ($this->book)($fields)->assertSessionHasErrors($error);
    Http::assertNothingSent();
})->with([
    'digit in first name' => [['first_name' => 'Jan2'], 'first_name'],
    'space in first name' => [['first_name' => 'Jan Paweł'], 'first_name'],
    'special char in surname' => [['last_name' => 'Kowalski!'], 'last_name'],
    'space in surname' => [['last_name' => 'Nowak Kowalska'], 'last_name'],
    'phone with +48' => [['phone' => '+48602118940'], 'phone'],
    'phone too short' => [['phone' => '60211894'], 'phone'],
    'phone with spaces' => [['phone' => '602 118 940'], 'phone'],
]);

it('accepts Polish letters and a double-barrelled surname, storing them capitalised', function () {
    ($this->book)(['typ' => 'pierwsza', 'first_name' => 'żaneta', 'last_name' => 'nowak-kowalska', 'phone' => '511222333']);
    $this->post(route('booking.confirm'), ['code' => lastSmsCode()]);

    $patient = Patient::withoutGlobalScopes()->latest('id')->first();

    expect($patient->first_name)->toBe('Żaneta')
        ->and($patient->last_name)->toBe('Nowak-Kowalska')
        ->and($patient->phone)->toBe('+48 511 222 333');
});

it('shows the fixed +48 prefix on the form', function () {
    $this->get(route('booking.show', [$this->physio, 'typ' => 'kolejna', 'data' => '2026-10-06', 'godzina' => '10:00']))
        ->assertSee('+48')
        ->assertSee('pattern="[0-9]{9}"', false);
});

/* ---------- deciding about a booking from another number ---------- */

function bookFromOtherNumber(): Appointment
{
    (test()->book)(['first_name' => 'Jan', 'last_name' => 'Kowalski', 'phone' => '699888777', 'email' => 'jan2@example.com']);
    test()->post(route('booking.confirm'), ['code' => lastSmsCode()]);

    return Appointment::withoutGlobalScopes()->sole();
}

it('keeps what was typed in the form on the visit', function () {
    expect(bookFromOtherNumber()->booking_details)->toBe(['first_name' => 'Jan', 'last_name' => 'Kowalski', 'email' => 'jan2@example.com']);
});

it('confirms on the matched card and can update its phone', function () {
    $appointment = bookFromOtherNumber();

    $this->actingAs($this->physio)
        ->post(route('booking.approve', $appointment), ['update_phone' => 1])
        ->assertSessionHasNoErrors();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Scheduled)
        ->and($this->existing->fresh()->phone)->toBe('+48 699 888 777');
});

it('confirms without touching the phone unless asked', function () {
    $appointment = bookFromOtherNumber();

    $this->actingAs($this->physio)->post(route('booking.approve', $appointment));

    expect($this->existing->fresh()->phone)->toBe('602 118 940');
});

it('moves the visit to a new card when it is someone else', function () {
    $appointment = bookFromOtherNumber();

    $this->actingAs($this->physio)
        ->post(route('booking.approve-new', $appointment))
        ->assertRedirect(route('appointments.show', $appointment));

    $appointment->refresh();
    $patient = Patient::withoutGlobalScopes()->find($appointment->patient_id);

    expect($patient->id)->not->toBe($this->existing->id)
        ->and($patient->first_name)->toBe('Jan')
        ->and($patient->last_name)->toBe('Kowalski')
        ->and($patient->phone)->toBe('+48 699 888 777')
        ->and($patient->email)->toBe('jan2@example.com')
        ->and($patient->operator_id)->toBe($this->physio->id)
        ->and($appointment->status)->toBe(AppointmentStatus::Scheduled)
        ->and($this->existing->fresh()->phone)->toBe('602 118 940');

    Http::assertSent(fn (Request $r) => ($r['to'] ?? null) === '48699888777' && str_contains($r['message'] ?? '', 'Potwierdzamy wizyte'));
});

it('shows the three choices only for a booking from another number', function () {
    $appointment = bookFromOtherNumber();

    $this->actingAs($this->physio)->get(route('appointments.show', $appointment))
        ->assertSee('Potwierdź — to ten pacjent')
        ->assertSee('To inna osoba — nowa karta')
        ->assertSee('zmień numer w karcie na +48 699 888 777');
});

it('keeps the new-card action to the owning physiotherapist', function () {
    $appointment = bookFromOtherNumber();

    $this->actingAs(User::factory()->operator()->create())
        ->post(route('booking.approve-new', $appointment))
        ->assertNotFound();

    expect(Patient::withoutGlobalScopes()->count())->toBe(1);
});
