<?php

namespace App\Http\Controllers;

use App\Enums\PatientAccessAction;
use App\Mail\VisitCardMail;
use App\Models\Appointment;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Messaging\MessagingNotConfigured;
use App\Services\Messaging\OutgoingMail;
use App\Services\VisitCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Throwable;

class VisitCardController extends Controller
{
    public function __construct(private readonly VisitCard $card) {}

    /** Opened in the browser, so it can be printed straight away or saved. */
    public function show(Appointment $appointment): Response
    {
        $this->authorize('view', $appointment);

        AuditLogService::log($appointment->patient, PatientAccessAction::VisitCardDownloaded);

        return response($this->card->pdf($appointment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->card->filename($appointment).'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function send(Appointment $appointment, OutgoingMail $mail): RedirectResponse
    {
        $this->authorize('view', $appointment);

        $patient = $appointment->patient;

        if (blank($patient->email)) {
            return back()->withErrors(['visit_card' => 'Pacjent nie ma adresu e-mail w karcie.']);
        }

        try {
            $mail->send($patient->email, new VisitCardMail(
                $appointment,
                User::findOrFail($appointment->operator_id),
                $this->card->pdf($appointment),
                $this->card->filename($appointment),
            ));
        } catch (MessagingNotConfigured $e) {
            return back()->withErrors(['visit_card' => $e->getMessage()]);
        } catch (Throwable) {
            return back()->withErrors(['visit_card' => 'Serwer poczty odrzucił wiadomość lub jest niedostępny. Sprawdź Ustawienia wysyłki.']);
        }

        AuditLogService::log($patient, PatientAccessAction::VisitCardSent);

        return back()->with('status', 'Wysłano kartę wizyty na '.$patient->email.'.');
    }
}
