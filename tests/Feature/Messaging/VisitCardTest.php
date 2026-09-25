<?php

use App\Enums\AppointmentStatus;
use App\Mail\VisitCardMail;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Services\Messaging\AppSettings;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    $this->operator = User::factory()->operator()->create(['practice_name' => 'FizjoRoom']);
    $this->patient = Patient::factory()->forOperator($this->operator)->create(['email' => 'pacjent@example.com']);

    $this->appointment = Appointment::factory()->forOperator($this->operator)->completed()->create([
        'patient_id' => $this->patient->id,
        'patient_recommendations' => 'Ćwiczenia rozciągające 2× dziennie.',
        'internal_notes' => 'Notatka tylko dla terapeuty.',
    ]);
});

it('renders the visit card as a PDF and logs it', function () {
    $response = $this->actingAs($this->operator)->get(route('visit-cards.show', $this->appointment));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr($response->getContent(), 0, 5))->toBe('%PDF-');

    expect($this->patient->accessLogs()->where('action', 'visit_card_downloaded')->count())->toBe(1);
});

it('puts recommendations and procedures on the card, never internal notes', function () {
    $html = view('pdf.visit-card', [
        'appointment' => $this->appointment,
        'patient' => $this->patient,
        'physiotherapist' => $this->operator,
        'nextVisits' => collect(),
    ])->render();

    expect($html)
        ->toContain('Ćwiczenia rozciągające 2× dziennie.')
        ->toContain('Terapia manualna')
        ->toContain('FizjoRoom')
        ->not->toContain('Notatka tylko dla terapeuty.');
});

it('e-mails the card as an attachment', function () {
    app(AppSettings::class)->put(['mail.host' => 'smtp.example.com', 'mail.from_address' => 'gabinet@example.com']);

    $this->actingAs($this->operator)
        ->post(route('visit-cards.send', $this->appointment))
        ->assertSessionHasNoErrors();

    Mail::assertSent(VisitCardMail::class, fn (VisitCardMail $mail) => $mail->hasTo('pacjent@example.com')
        && count($mail->attachments()) === 1);

    expect($this->patient->accessLogs()->where('action', 'visit_card_sent')->count())->toBe(1);
});

it('refuses to e-mail without a configured server', function () {
    $this->actingAs($this->operator)
        ->post(route('visit-cards.send', $this->appointment))
        ->assertSessionHasErrors('visit_card');

    Mail::assertNothingSent();
});

it('keeps another operator away from the card', function () {
    $other = User::factory()->operator()->create();

    $this->actingAs($other)->get(route('visit-cards.show', $this->appointment))->assertNotFound();
    $this->actingAs($other)->post(route('visit-cards.send', $this->appointment))->assertNotFound();
});

it('shows the card and reminder controls on the visit page', function () {
    $upcoming = Appointment::factory()->forOperator($this->operator)->create([
        'patient_id' => $this->patient->id,
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHour(),
        'status' => AppointmentStatus::Scheduled,
    ]);

    $this->actingAs($this->operator)
        ->get(route('appointments.show', $upcoming))
        ->assertOk()
        ->assertSee('Otwórz / drukuj PDF')
        ->assertSee('Przypomnienie:');
});
