<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Services\Messaging\AppSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-05 07:00')); // Monday

    $this->admin = User::factory()->admin()->create();
    $this->operator = User::factory()->operator()->create();
    $this->patient = Patient::factory()->forOperator($this->operator)->create(['last_name' => 'Kowalski', 'phone' => '602118940']);
    $this->massage = Service::where('name', 'Masaż')->sole();
});

it('starts with physiotherapy as default and massage', function () {
    expect(Service::default()->name)->toBe('Fizjoterapia')
        ->and($this->massage->online_bookable)->toBeTrue();
});

it('lets only the admin manage kinds of visit', function () {
    $this->actingAs($this->operator)->get(route('admin.services.index'))->assertForbidden();

    $this->actingAs($this->admin)
        ->post(route('admin.services.store'), ['name' => 'Masaż sportowy', 'duration_minutes' => 45, 'online_bookable' => 1])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)->get(route('admin.services.index'))->assertOk()->assertSee('Masaż sportowy');
});

it('keeps the default service active', function () {
    $default = Service::default();

    $this->actingAs($this->admin)
        ->put(route('admin.services.update', $default), ['name' => 'Fizjoterapia', 'duration_minutes' => 50, 'active' => 0])
        ->assertSessionHasNoErrors();

    expect($default->fresh())->active->toBeTrue()->duration_minutes->toBe(50);
});

it('books a massage from the panel and shows it on the visit and calendar', function () {
    $this->actingAs($this->operator)
        ->post(route('appointments.store'), [
            'patient_id' => $this->patient->id,
            'service_id' => $this->massage->id,
            'starts_at' => '2026-10-05 10:00',
            'duration_minutes' => 60,
        ])
        ->assertSessionHasNoErrors();

    $appointment = Appointment::sole();

    expect($appointment->service_id)->toBe($this->massage->id);

    $this->actingAs($this->operator)->get(route('appointments.show', $appointment))->assertSee('Masaż');
    $this->actingAs($this->operator)->getJson(route('appointments.calendar-feed'))
        ->assertJsonFragment(['title' => 'Masaż · '.$this->patient->last_name.' '.$this->patient->first_name]);
});

it('defaults to physiotherapy and refuses a switched-off service', function () {
    $this->actingAs($this->operator)->post(route('appointments.store'), [
        'patient_id' => $this->patient->id, 'starts_at' => '2026-10-05 10:00', 'duration_minutes' => 30,
    ]);
    expect(Appointment::sole()->service_id)->toBe(Service::default()->id);

    $this->massage->update(['active' => false]);

    $this->actingAs($this->operator)->post(route('appointments.store'), [
        'patient_id' => $this->patient->id, 'service_id' => $this->massage->id, 'starts_at' => '2026-10-05 11:00', 'duration_minutes' => 30,
    ])->assertSessionHasErrors('service_id');
});

it('offers massage in online booking with its own length, rounded to the slot', function () {
    Http::fake(['api.smsapi.pl/*' => Http::response(['count' => 1])]);
    app(AppSettings::class)->put(['sms.token' => 'x']);
    $this->massage->update(['duration_minutes' => 50]);
    $this->operator->forceFill(['online_booking_enabled' => true])->save();

    $this->get(route('booking.show', $this->operator))->assertSee('Masaż')->assertSee('Jestem już pacjentem');

    $this->post(route('booking.request', $this->operator), [
        'typ' => 'u'.$this->massage->id,
        'starts_at' => '2026-10-06 10:00',
        'first_name' => 'Jan', 'last_name' => 'Kowalski', 'phone' => '602 118 940', 'consent' => 1,
    ])->assertRedirect(route('booking.code'));

    $sms = collect(Http::recorded())->map(fn ($pair) => $pair[0]['message'] ?? '')->last();
    preg_match('/(\d{6})/', $sms, $code);
    $this->post(route('booking.confirm'), ['code' => $code[1]]);

    $appointment = Appointment::withoutGlobalScopes()->sole();

    expect($appointment->service_id)->toBe($this->massage->id)
        ->and($appointment->ends_at->format('H:i'))->toBe('11:00');   // 50 min → two 30-minute slots
});

it('hides services not bookable online', function () {
    app(AppSettings::class)->put(['sms.token' => 'x']);
    $this->massage->update(['online_bookable' => false]);
    $this->operator->forceFill(['online_booking_enabled' => true])->save();

    $this->get(route('booking.show', $this->operator))->assertDontSee('Masaż');
});
