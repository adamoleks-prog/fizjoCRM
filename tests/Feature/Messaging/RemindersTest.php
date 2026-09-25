<?php

use App\Enums\AppointmentStatus;
use App\Mail\AppointmentReminderMail;
use App\Mail\TestMessageMail;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\Patient;
use App\Models\User;
use App\Services\Messaging\AppSettings;
use App\Services\Messaging\PhoneNumber;
use App\Services\Messaging\ReminderMessage;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Http::preventStrayRequests();
    Mail::fake();

    $this->travelTo(now()->setTime(12, 0));

    $this->operator = User::factory()->operator()->create([
        'practice_name' => 'FizjoRoom',
        'practice_address' => 'ul. Testowa 1',
        'practice_phone' => '500 100 200',
    ]);

    $this->patient = Patient::factory()->forOperator($this->operator)->create([
        'phone' => '602 118 940',
        'email' => 'pacjent@example.com',
    ]);

    $this->appointment = Appointment::factory()->forOperator($this->operator)->create([
        'patient_id' => $this->patient->id,
        'starts_at' => now()->addDay()->setTime(10, 0),
        'ends_at' => now()->addDay()->setTime(11, 0),
        'status' => AppointmentStatus::Scheduled,
    ]);

    $this->configure = function (bool $sms = true, bool $email = true): void {
        app(AppSettings::class)->put([
            'mail.host' => 'smtp.example.com',
            'mail.from_address' => 'gabinet@example.com',
            'sms.token' => 'sms-test-token',
            'reminders.sms_enabled' => $sms ? '1' : '0',
            'reminders.email_enabled' => $email ? '1' : '0',
            'reminders.hours_before' => '24',
        ]);
    };
});

it('writes the SMS without the reason for the visit', function () {
    $text = ReminderMessage::sms($this->appointment, $this->operator);

    expect($text)->toBe('Przypomnienie: wizyta w FizjoRoom jutro '.$this->appointment->starts_at->format('d.m').' o 10:00. ul. Testowa 1. Odwołanie: tel. 500 100 200.');
});

it('normalises Polish phone numbers', function () {
    expect(PhoneNumber::normalize('602 118 940'))->toBe('48602118940')
        ->and(PhoneNumber::normalize('+48 602-118-940'))->toBe('48602118940')
        ->and(PhoneNumber::normalize('0048602118940'))->toBe('48602118940')
        ->and(PhoneNumber::normalize('12345'))->toBeNull();
});

it('sends due reminders by SMS and e-mail, once', function () {
    ($this->configure)();
    Http::fake(['api.smsapi.pl/*' => Http::response(['count' => 1, 'list' => [['id' => 'x', 'status' => 'QUEUE']]])]);

    $this->artisan('reminders:send')->assertSuccessful();
    $this->artisan('reminders:send')->assertSuccessful();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $r) => $r->url() === 'https://api.smsapi.pl/sms.do'
        && $r->hasHeader('Authorization', 'Bearer sms-test-token')
        && $r['to'] === '48602118940'
        && (int) $r['normalize'] === 1);
    Mail::assertSent(AppointmentReminderMail::class, fn ($m) => $m->hasTo('pacjent@example.com'));
    Mail::assertSentCount(1);

    expect(AppointmentReminder::where('status', 'sent')->count())->toBe(2)
        ->and($this->appointment->fresh()->reminder_sent_at)->not->toBeNull();
});

it('does not remind visits outside the window, cancelled ones or patients who opted out', function () {
    ($this->configure)();
    Http::fake();

    $this->appointment->update(['starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour()]);

    $cancelled = Appointment::factory()->forOperator($this->operator)->create([
        'patient_id' => $this->patient->id,
        'starts_at' => now()->addHours(20),
        'ends_at' => now()->addHours(21),
        'status' => AppointmentStatus::Cancelled,
    ]);

    $optedOut = Patient::factory()->forOperator($this->operator)->create(['reminders_enabled' => false]);
    Appointment::factory()->forOperator($this->operator)->create([
        'patient_id' => $optedOut->id,
        'starts_at' => now()->addHours(20),
        'ends_at' => now()->addHours(21),
        'status' => AppointmentStatus::Scheduled,
    ]);

    $this->artisan('reminders:send')->assertSuccessful();

    Http::assertNothingSent();
    Mail::assertNothingSent();
});

it('does nothing while both channels are switched off', function () {
    ($this->configure)(sms: false, email: false);
    Http::fake();

    $this->artisan('reminders:send')->assertSuccessful();

    Http::assertNothingSent();
    Mail::assertNothingSent();
    expect($this->appointment->fresh()->reminder_sent_at)->toBeNull();
});

it('records a failed SMS with the gateway reason', function () {
    ($this->configure)(email: false);
    Http::fake(['api.smsapi.pl/*' => Http::response(['error' => 101, 'message' => 'Authorization failed'])]);

    $this->artisan('reminders:send')->assertSuccessful();

    expect(AppointmentReminder::sole())
        ->status->toBe('failed')
        ->error->toContain('101');
});

it('reminds again after the visit is moved', function () {
    DB::table('appointments')->where('id', $this->appointment->id)->update(['reminder_sent_at' => now()]);

    $this->appointment->refresh()->update([
        'starts_at' => now()->addDay()->setTime(14, 0),
        'ends_at' => now()->addDay()->setTime(15, 0),
    ]);

    expect($this->appointment->fresh()->reminder_sent_at)->toBeNull();
});

it('lets the physiotherapist send a reminder by hand', function () {
    ($this->configure)(sms: false);

    $this->actingAs($this->operator)
        ->post(route('appointments.reminder', $this->appointment))
        ->assertSessionHasNoErrors();

    Mail::assertSent(AppointmentReminderMail::class);

    $this->actingAs(User::factory()->operator()->create())
        ->post(route('appointments.reminder', $this->appointment))
        ->assertNotFound();
});

/* ---------- admin settings ---------- */

it('keeps the settings page for admins only', function () {
    $this->actingAs($this->operator)->get(route('admin.messaging.edit'))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.messaging.edit'))->assertOk();
});

it('stores secrets encrypted and never shows them back', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('admin.messaging.update'), [
        'mail_host' => 'mail.example.com',
        'mail_port' => 587,
        'mail_encryption' => 'tls',
        'mail_username' => 'gabinet',
        'mail_password' => 'super-tajne-haslo',
        'mail_from_address' => 'gabinet@example.com',
        'sms_token' => 'token-sms-123',
        'sms_sender' => 'FizjoRoom',
        'reminders_sms_enabled' => 1,
        'reminders_email_enabled' => 0,
        'reminders_hours_before' => 24,
    ])->assertSessionHasNoErrors();

    $raw = DB::table('settings')->pluck('value', 'key');

    expect($raw['mail.password'])->not->toBe('super-tajne-haslo')
        ->and($raw['sms.token'])->not->toBe('token-sms-123')
        ->and(app(AppSettings::class)->get('mail.password'))->toBe('super-tajne-haslo');

    $this->actingAs($admin)->get(route('admin.messaging.edit'))
        ->assertOk()
        ->assertDontSee('super-tajne-haslo')
        ->assertDontSee('token-sms-123')
        ->assertSee('zapisane');

    // Saving again with empty secret fields keeps them.
    $this->actingAs($admin)->put(route('admin.messaging.update'), [
        'mail_host' => 'mail.example.com',
        'mail_encryption' => 'tls',
        'mail_from_address' => 'gabinet@example.com',
        'reminders_hours_before' => 24,
    ])->assertSessionHasNoErrors();

    expect(app(AppSettings::class)->get('sms.token'))->toBe('token-sms-123');
});

it('sends a test e-mail through the configured server', function () {
    ($this->configure)();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.messaging.test-email'), ['test_email' => 'admin@example.com'])
        ->assertSessionHasNoErrors();

    Mail::assertSent(TestMessageMail::class, fn ($m) => $m->hasTo('admin@example.com'));
    expect(config('mail.mailers.smtp.host'))->toBe('smtp.example.com');
});

it('explains when the mail server is not configured', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.messaging.test-email'), ['test_email' => 'admin@example.com'])
        ->assertSessionHasErrors('test_email');

    Mail::assertNothingSent();
});
