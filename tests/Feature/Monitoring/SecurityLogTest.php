<?php

use App\Enums\SecurityEventType;
use App\Jobs\SendMonitoringAlert;
use App\Models\Patient;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Messaging\AppSettings;
use App\Services\Monitoring\SecurityAlerts;
use App\Services\Monitoring\SecurityLog;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

function eventsOf(SecurityEventType $type)
{
    return SecurityEvent::where('type', $type)->get();
}

test('a failed login is logged with the typed e-mail and never the password', function () {
    $this->post('/login', ['email' => 'Ktos@Example.com', 'password' => 'tajne-haslo-123'])->assertSessionHasErrors('email');

    $event = eventsOf(SecurityEventType::LoginFailed)->sole();
    expect($event->email)->toBe('ktos@example.com')
        ->and($event->ip_address)->toBe('127.0.0.1')
        ->and($event->details)->toBe(['account_exists' => false])
        ->and(json_encode(SecurityEvent::first()->toArray()))->not->toContain('tajne-haslo-123');
});

test('many failed logins from one address send a single alert', function () {
    foreach (range(1, SecurityAlerts::FAILED_LOGINS_PER_IP + 3) as $i) {
        // Different e-mails, so the per-account lockout does not stop the attempts.
        $this->post('/login', ['email' => "user{$i}@example.com", 'password' => 'zle']);
    }

    expect(eventsOf(SecurityEventType::LoginFailed))->toHaveCount(SecurityAlerts::FAILED_LOGINS_PER_IP + 3);
    Queue::assertPushed(SendMonitoringAlert::class, 1);
    Queue::assertPushed(SendMonitoringAlert::class, fn ($job) => str_contains($job->subject, 'Wiele nieudanych logowań'));
});

test('a lockout is logged and alerted', function () {
    $user = User::factory()->operator()->create();

    foreach (range(1, 6) as $i) {
        $this->post('/login', ['email' => $user->email, 'password' => 'zle']);
    }

    expect(eventsOf(SecurityEventType::Lockout))->not->toBeEmpty();
    Queue::assertPushed(SendMonitoringAlert::class, fn ($job) => str_contains($job->subject, 'Zablokowano logowanie'));
});

test('a successful login after several failures raises an alert', function () {
    $user = User::factory()->operator()->create();

    foreach (range(1, 3) as $i) {
        $this->post('/login', ['email' => $user->email, 'password' => 'zle']);
    }
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();

    $login = eventsOf(SecurityEventType::Login)->sole();
    expect($login->details['failures_before'])->toBe(3);
    Queue::assertPushed(SendMonitoringAlert::class, fn ($job) => str_contains($job->subject, 'po serii nieudanych prób'));
});

test('an administrator logging in from a new address is alerted, a known address is not', function () {
    $admin = User::factory()->admin()->create();

    // First ever login — nothing to compare with.
    $this->post('/login', ['email' => $admin->email, 'password' => 'password']);
    $this->post('/logout');
    Queue::assertNotPushed(SendMonitoringAlert::class);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->post('/login', ['email' => $admin->email, 'password' => 'password']);

    Queue::assertPushed(SendMonitoringAlert::class, fn ($job) => str_contains($job->subject, 'nowego adresu IP'));
    expect(eventsOf(SecurityEventType::Logout))->toHaveCount(1);
});

test('an operator opening an admin page is logged as refused access', function () {
    $operator = User::factory()->operator()->create();

    $this->actingAs($operator)->get(route('admin.system.show'))->assertForbidden();

    $event = eventsOf(SecurityEventType::AccessDenied)->sole();
    expect($event->user_id)->toBe($operator->id)
        ->and($event->path)->toBe('/admin/system')
        ->and($event->details)->toBe(['admin_area' => true]);
});

test('opening another physiotherapist\'s patient is logged', function () {
    $operator = User::factory()->operator()->create();
    $other = Patient::factory()->forOperator(User::factory()->operator()->create())->create();

    $this->actingAs($operator)->get(route('patients.show', $other))->assertNotFound();

    $event = eventsOf(SecurityEventType::RecordNotFound)->sole();
    expect($event->details['model'])->toBe('Patient')
        ->and($event->details['ids'])->toBe([(string) $other->id]);
});

test('scanner addresses are logged, ordinary missing pages are not', function () {
    $this->get('/wp-login.php')->assertNotFound();
    $this->get('/.env')->assertNotFound();
    $this->get('/nie-ma-takiej-strony')->assertNotFound();

    expect(eventsOf(SecurityEventType::Probe)->pluck('path')->all())->toBe(['/wp-login.php', '/.env']);
});

test('one scanner cannot flood the log', function () {
    foreach (range(1, SecurityLog::MAX_PER_HOUR + 10) as $i) {
        $this->get("/wp-admin/{$i}.php");
    }

    expect(eventsOf(SecurityEventType::Probe))->toHaveCount(SecurityLog::MAX_PER_HOUR);
});

test('the booking honeypot is logged', function () {
    app(AppSettings::class)->put(['booking.sms_verification' => '0']);
    $physio = User::factory()->operator()->create(['online_booking_enabled' => true]);

    $this->post(route('booking.request', $physio), ['website' => 'http://spam.example'])->assertStatus(422);

    expect(eventsOf(SecurityEventType::BookingHoneypot))->toHaveCount(1);
});

test('changing admin settings logs the field names but not their values', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('admin.messaging.update'), [
        'mail_encryption' => 'tls',
        'reminders_hours_before' => 24,
        'sms_token' => 'super-tajny-token',
    ])->assertRedirect();

    $event = eventsOf(SecurityEventType::SettingsChanged)->sole();
    expect($event->details['changed'])->toContain('sms.token')
        ->and(json_encode($event->toArray()))->not->toContain('super-tajny-token');
    Queue::assertPushed(SendMonitoringAlert::class, fn ($job) => str_contains($job->subject, 'Zmieniono ustawienia'));
});

test('a password change is logged and alerted', function () {
    $user = User::factory()->operator()->create();

    $this->actingAs($user)->put('/password', [
        'current_password' => 'password',
        'password' => 'nowe-Haslo-2026!',
        'password_confirmation' => 'nowe-Haslo-2026!',
    ])->assertSessionHasNoErrors();

    expect(eventsOf(SecurityEventType::PasswordChanged))->toHaveCount(1);
    Queue::assertPushed(SendMonitoringAlert::class, fn ($job) => str_contains($job->subject, 'Zmiana hasła'));
});

test('alerts can be switched off', function () {
    app(AppSettings::class)->put(['monitoring.alerts_enabled' => '0']);
    $user = User::factory()->operator()->create();

    $this->actingAs($user)->put('/password', [
        'current_password' => 'password',
        'password' => 'nowe-Haslo-2026!',
        'password_confirmation' => 'nowe-Haslo-2026!',
    ]);

    expect(eventsOf(SecurityEventType::PasswordChanged))->toHaveCount(1);
    Queue::assertNotPushed(SendMonitoringAlert::class);
});
