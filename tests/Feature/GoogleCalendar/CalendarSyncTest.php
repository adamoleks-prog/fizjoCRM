<?php

use App\Jobs\SyncAppointmentToGoogleCalendar;
use App\Models\Appointment;
use App\Models\GoogleCalendarToken;
use App\Models\Patient;
use App\Models\User;
use App\Services\GoogleCalendar\CalendarSynchronizer;
use Illuminate\Support\Facades\Queue;

class FakeCalendarSynchronizer implements CalendarSynchronizer
{
    public array $upserted = [];

    public array $deleted = [];

    public function __construct(public ?string $returnEventId = 'google-event-123') {}

    public function upsertEvent(Appointment $appointment): ?string
    {
        $this->upserted[] = $appointment->id;

        return $this->returnEventId;
    }

    public function deleteEvent(Appointment $appointment): void
    {
        $this->deleted[] = $appointment->id;
    }
}

function connectGoogle(User $operator): GoogleCalendarToken
{
    return $operator->googleCalendarToken()->create([
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'expires_at' => now()->addHour(),
    ]);
}

it('dispatches a sync job when an appointment is created', function () {
    Queue::fake();

    $operator = User::factory()->operator()->create();
    $patient = Patient::factory()->forOperator($operator)->create();

    $this->actingAs($operator)->post(route('appointments.store'), [
        'patient_id' => $patient->id,
        'starts_at' => '2026-10-20 09:00:00',
        'duration_minutes' => 30,
    ])->assertSessionHasNoErrors();

    Queue::assertPushed(SyncAppointmentToGoogleCalendar::class);
});

it('dispatches a sync job when an appointment is deleted', function () {
    Queue::fake();

    $operator = User::factory()->operator()->create();
    $appointment = Appointment::factory()->forOperator($operator)->create();

    $this->actingAs($operator)->delete(route('appointments.destroy', $appointment));

    Queue::assertPushed(
        SyncAppointmentToGoogleCalendar::class,
        fn (SyncAppointmentToGoogleCalendar $job) => $job->deleted === true,
    );
});

it('stores the google event id returned by the sync', function () {
    $fake = new FakeCalendarSynchronizer;
    $this->app->instance(CalendarSynchronizer::class, $fake);

    $operator = User::factory()->operator()->create();
    connectGoogle($operator);
    $appointment = Appointment::factory()->forOperator($operator)->create();

    (new SyncAppointmentToGoogleCalendar($appointment->id))->handle($fake);

    expect($fake->upserted)->toContain($appointment->id)
        ->and($appointment->fresh()->google_event_id)->toBe('google-event-123');
});

it('does nothing when the operator has no connected calendar', function () {
    $fake = new FakeCalendarSynchronizer;
    $this->app->instance(CalendarSynchronizer::class, $fake);

    $operator = User::factory()->operator()->create();
    $appointment = Appointment::factory()->forOperator($operator)->create();

    (new SyncAppointmentToGoogleCalendar($appointment->id))->handle($fake);

    expect($fake->upserted)->toBeEmpty()
        ->and($appointment->fresh()->google_event_id)->toBeNull();
});

it('deletes the remote event for a removed appointment', function () {
    $fake = new FakeCalendarSynchronizer;
    $this->app->instance(CalendarSynchronizer::class, $fake);

    $operator = User::factory()->operator()->create();
    connectGoogle($operator);
    $appointment = Appointment::factory()->forOperator($operator)->create(['google_event_id' => 'abc']);
    $appointment->delete();

    (new SyncAppointmentToGoogleCalendar($appointment->id, deleted: true))->handle($fake);

    expect($fake->deleted)->toContain($appointment->id);
});

it('encrypts google tokens at rest', function () {
    $operator = User::factory()->operator()->create();
    $token = connectGoogle($operator);

    $raw = DB::table('google_calendar_tokens')->where('id', $token->id)->first();

    expect($raw->access_token)->not->toBe('access-token')
        ->and($token->fresh()->access_token)->toBe('access-token');
});

it('shows the connect prompt when the calendar is not linked', function () {
    $operator = User::factory()->operator()->create();
    config(['services.google.client_id' => 'test-client-id']);

    $this->actingAs($operator)
        ->get(route('google-calendar.edit'))
        ->assertOk()
        ->assertSee('Połącz z Google Calendar');
});

it('lets an operator disconnect their calendar', function () {
    $operator = User::factory()->operator()->create();
    connectGoogle($operator);

    $this->actingAs($operator)
        ->delete(route('google-calendar.destroy'))
        ->assertRedirect(route('google-calendar.edit'));

    expect($operator->fresh()->googleCalendarToken)->toBeNull()
        ->and($operator->fresh()->google_calendar_connected_at)->toBeNull();
});

it('sends only the upcoming visits the user runs when asked', function () {
    $operator = User::factory()->operator()->create();
    connectGoogle($operator);
    $patient = Patient::factory()->forOperator($operator)->create();

    Queue::fake();

    $upcoming = Appointment::factory()->forOperator($operator)->create(['patient_id' => $patient->id, 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour()]);
    Appointment::factory()->forOperator($operator)->create(['patient_id' => $patient->id, 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDays(2)->addHour()]);
    Appointment::factory()->create(['starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour()]);

    Queue::fake();

    $this->actingAs($operator)
        ->post(route('google-calendar.sync'))
        ->assertRedirect(route('google-calendar.edit'));

    Queue::assertPushed(SyncAppointmentToGoogleCalendar::class, 1);
    Queue::assertPushed(SyncAppointmentToGoogleCalendar::class, fn ($job) => $job->appointmentId === $upcoming->id);
});

it('lets an admin be the treating physiotherapist of a patient', function () {
    $admin = User::factory()->admin()->create(['name' => 'Szef Gabinetu']);

    $this->actingAs($admin)
        ->get(route('patients.create'))
        ->assertSee('Szef Gabinetu (administrator)');

    $this->actingAs($admin)
        ->post(route('patients.store'), ['first_name' => 'Test', 'last_name' => 'Testowy', 'operator_id' => $admin->id, 'reminders_enabled' => 1])
        ->assertSessionHasNoErrors();

    expect(Patient::where('last_name', 'Testowy')->sole()->operator_id)->toBe($admin->id);
});
