<?php

use App\Enums\PatientAccessAction;
use App\Enums\SecurityEventType;
use App\Jobs\SendMonitoringAlert;
use App\Mail\DailyMonitoringReportMail;
use App\Mail\MonitoringAlertMail;
use App\Models\AppError;
use App\Models\Patient;
use App\Models\PatientAccessLog;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Messaging\AppSettings;
use App\Services\Messaging\OutgoingMail;
use App\Services\Monitoring\ErrorTracker;
use App\Services\Monitoring\Heartbeat;
use App\Services\Monitoring\MonitoringRecipients;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;

function configureMail(): void
{
    app(AppSettings::class)->put(['mail.host' => 'smtp.example.com', 'mail.from_address' => 'gabinet@example.com']);
}

test('an application error is stored once, counted and alerted once an hour', function () {
    Queue::fake();
    Route::middleware('web')->get('/_test/boom', fn () => throw new RuntimeException('Coś poszło nie tak dla jan@example.com, tel. 602 118 940'));

    $this->get('/_test/boom')->assertStatus(500);
    $this->get('/_test/boom')->assertStatus(500);

    $error = AppError::sole();
    expect($error->occurrences)->toBe(2)
        ->and($error->exception)->toBe(RuntimeException::class)
        ->and($error->url)->toBe('/_test/boom')
        ->and($error->message)->not->toContain('jan@example.com')
        ->and($error->message)->not->toContain('602 118 940');

    Queue::assertPushed(SendMonitoringAlert::class, 1);
});

test('a resolved error that comes back is alerted again', function () {
    Queue::fake();
    $tracker = app(ErrorTracker::class);
    $e = new LogicException('x');

    $tracker->capture($e);
    AppError::sole()->update(['resolved_at' => now()]);
    $tracker->capture($e);

    expect(AppError::sole()->resolved_at)->toBeNull();
    Queue::assertPushed(SendMonitoringAlert::class, 2);
});

test('database errors keep only the error code', function () {
    $e = new QueryException('mysql', 'insert into patients values (?)', ['Kowalski'], new Exception('Duplicate Kowalski'));

    expect(ErrorTracker::clean($e))->not->toContain('Kowalski');
});

test('the alert job e-mails every administrator and the extra address', function () {
    Mail::fake();
    configureMail();
    User::factory()->admin()->create(['email' => 'szef@example.com']);
    User::factory()->operator()->create(['email' => 'fizjo@example.com']);
    app(AppSettings::class)->put(['monitoring.alert_email' => 'it@example.com']);

    (new SendMonitoringAlert('Test', ['linia'], 'https://example.com'))->handle(app(OutgoingMail::class), app(MonitoringRecipients::class));

    Mail::assertSent(MonitoringAlertMail::class, 2);
    Mail::assertSent(MonitoringAlertMail::class, fn ($mail) => $mail->hasTo('szef@example.com'));
    Mail::assertSent(MonitoringAlertMail::class, fn ($mail) => $mail->hasTo('it@example.com'));
    Mail::assertNotSent(MonitoringAlertMail::class, fn ($mail) => $mail->hasTo('fizjo@example.com'));
});

test('the daily report goes to administrators', function () {
    Mail::fake();
    configureMail();
    User::factory()->admin()->create();
    SecurityEvent::create(['type' => SecurityEventType::Probe, 'severity' => 'warning', 'ip_address' => '198.51.100.1', 'path' => '/.env']);

    $this->artisan('monitoring:daily-report')->assertSuccessful();

    Mail::assertSent(DailyMonitoringReportMail::class, fn ($mail) => str_contains($mail->render(), '198.51.100.1'));
});

test('the daily report can be switched off', function () {
    Mail::fake();
    configureMail();
    User::factory()->admin()->create();
    app(AppSettings::class)->put(['monitoring.daily_report' => '0']);

    $this->artisan('monitoring:daily-report')->assertSuccessful();

    Mail::assertNothingSent();
});

test('heartbeats tell whether the background processes are alive', function () {
    expect(Heartbeat::isAlive(Heartbeat::QUEUE))->toBeFalse();

    Heartbeat::beat(Heartbeat::QUEUE);
    expect(Heartbeat::isAlive(Heartbeat::QUEUE))->toBeTrue();

    $this->travel(Heartbeat::STALE_AFTER_MINUTES + 1)->minutes();
    expect(Heartbeat::isAlive(Heartbeat::QUEUE))->toBeFalse();
});

test('the external ping reports a dead queue on the fail address', function () {
    Http::fake();
    app(AppSettings::class)->put(['monitoring.healthcheck_url' => 'https://hc-ping.com/abc']);

    $this->artisan('monitoring:ping')->assertSuccessful();
    Http::assertSent(fn ($request) => $request->url() === 'https://hc-ping.com/abc/fail');

    Heartbeat::beat(Heartbeat::QUEUE);
    $this->artisan('monitoring:ping')->assertSuccessful();
    Http::assertSent(fn ($request) => $request->url() === 'https://hc-ping.com/abc');
});

test('old security events are pruned, the patient access log is kept', function () {
    $patient = Patient::factory()->create();
    $old = SecurityEvent::create(['type' => SecurityEventType::Probe, 'severity' => 'warning']);
    $old->forceFill(['created_at' => now()->subMonths(13)])->save();
    SecurityEvent::create(['type' => SecurityEventType::Probe, 'severity' => 'warning']);
    $access = PatientAccessLog::create(['patient_id' => $patient->id, 'user_id' => $patient->operator_id, 'action' => PatientAccessAction::Viewed]);
    $access->forceFill(['created_at' => now()->subYears(6)])->save();

    $this->artisan('monitoring:prune')->assertSuccessful();

    expect(SecurityEvent::count())->toBe(1)->and(PatientAccessLog::count())->toBe(1);
});

test('administrators see the monitoring pages, operators do not', function () {
    $admin = User::factory()->admin()->create();
    $operator = User::factory()->operator()->create();
    $patient = Patient::factory()->forOperator($operator)->create(['last_name' => 'Zielińska']);
    PatientAccessLog::create(['patient_id' => $patient->id, 'user_id' => $operator->id, 'action' => PatientAccessAction::Viewed, 'ip_address' => '10.0.0.1']);
    SecurityEvent::create(['type' => SecurityEventType::LoginFailed, 'severity' => 'warning', 'email' => 'x@example.com', 'ip_address' => '198.51.100.9']);
    AppError::create(['fingerprint' => sha1('x'), 'exception' => RuntimeException::class, 'message' => 'Awaria', 'first_seen_at' => now(), 'last_seen_at' => now()]);

    $this->actingAs($admin)->get(route('admin.system.show'))->assertOk()->assertSee('Kontrola')->assertSee('Awaria');
    $this->actingAs($admin)->get(route('admin.security.index', ['ip' => '198.51.100.9']))->assertOk()->assertSee('x@example.com');
    $this->actingAs($admin)->get(route('admin.access-log.index', ['patient' => 'zieli']))->assertOk()->assertSee('Zielińska')->assertSee('Otwarcie karty');

    foreach (['admin.system.show', 'admin.security.index', 'admin.access-log.index'] as $route) {
        $this->actingAs($operator)->get(route($route))->assertForbidden();
    }
});

test('the administrator saves monitoring settings and resolves an error', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $error = AppError::create(['fingerprint' => sha1('y'), 'exception' => RuntimeException::class, 'first_seen_at' => now(), 'last_seen_at' => now()]);

    $this->actingAs($admin)->put(route('admin.system.update'), [
        'alerts_enabled' => '1',
        'daily_report' => '0',
        'alert_email' => 'it@example.com',
        'healthcheck_url' => 'https://hc-ping.com/abc',
    ])->assertRedirect(route('admin.system.show'));

    $settings = app(AppSettings::class);
    expect($settings->get('monitoring.daily_report'))->toBe('0')
        ->and($settings->get('monitoring.healthcheck_url'))->toBe('https://hc-ping.com/abc');

    $this->actingAs($admin)->post(route('admin.system.errors.resolve', $error))->assertRedirect();
    expect($error->fresh()->resolved_at)->not->toBeNull();
});
