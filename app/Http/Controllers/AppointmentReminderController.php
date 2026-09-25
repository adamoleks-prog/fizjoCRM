<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\Messaging\ReminderSender;
use Illuminate\Http\RedirectResponse;

class AppointmentReminderController extends Controller
{
    /** Sends a reminder now — after a reschedule, or when the automatic one failed. */
    public function store(Appointment $appointment, ReminderSender $sender): RedirectResponse
    {
        $this->authorize('update', $appointment);

        $results = $sender->send($appointment);

        if ($results === []) {
            return back()->withErrors([
                'reminder' => 'Brak kanału do wysłania: włącz SMS lub e-mail w Ustawieniach wysyłki i uzupełnij telefon lub e-mail pacjenta.',
            ]);
        }

        $failed = collect($results)->where('status', 'failed');

        if ($failed->count() === count($results)) {
            return back()->withErrors(['reminder' => 'Nie udało się wysłać: '.$failed->pluck('error')->implode(' ')]);
        }

        return back()->with('status', 'Wysłano przypomnienie: '.collect($results)->where('status', 'sent')->map->channelLabel()->implode(', ').'.');
    }
}
