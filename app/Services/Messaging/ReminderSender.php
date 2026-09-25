<?php

namespace App\Services\Messaging;

use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\Patient;
use App\Models\Scopes\OperatorScope;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sends a visit reminder over every channel that is switched on in the settings
 * and that the patient has contact details for.
 */
class ReminderSender
{
    public function __construct(
        private readonly AppSettings $settings,
        private readonly OutgoingMail $mail,
        private readonly SmsGateway $sms,
    ) {}

    /**
     * @return array<int, AppointmentReminder> one entry per channel tried
     */
    public function send(Appointment $appointment): array
    {
        $patient = Patient::withoutGlobalScope(OperatorScope::class)->find($appointment->patient_id);
        $physiotherapist = User::find($appointment->operator_id);

        if (! $patient || ! $physiotherapist) {
            return [];
        }

        $results = [];

        if ($this->settings->bool('reminders.sms_enabled') && filled($patient->phone)) {
            $results[] = $this->attempt($appointment, 'sms', fn () => $this->sms->send(
                $patient->phone,
                ReminderMessage::sms($appointment, $physiotherapist),
            ));
        }

        if ($this->settings->bool('reminders.email_enabled') && filled($patient->email)) {
            $results[] = $this->attempt($appointment, 'email', fn () => $this->mail->send(
                $patient->email,
                new AppointmentReminderMail($appointment, $physiotherapist),
            ));
        }

        if ($results !== []) {
            // Written directly: a reminder is not a change to the visit, so it must
            // not trigger the calendar sync the model observer runs on update.
            DB::table('appointments')->where('id', $appointment->id)->update(['reminder_sent_at' => now()]);
        }

        return $results;
    }

    /**
     * Whether a reminder could go out at all — at least one channel on and usable.
     */
    public function isAvailableFor(Patient $patient): bool
    {
        return ($this->settings->bool('reminders.sms_enabled') && $this->sms->isConfigured() && filled($patient->phone))
            || ($this->settings->bool('reminders.email_enabled') && $this->mail->isConfigured() && filled($patient->email));
    }

    private function attempt(Appointment $appointment, string $channel, callable $send): AppointmentReminder
    {
        $reminder = new AppointmentReminder;
        $reminder->operator_id = $appointment->operator_id;
        $reminder->appointment_id = $appointment->id;
        $reminder->channel = $channel;

        try {
            $send();
            $reminder->status = 'sent';
        } catch (MessagingNotConfigured|SmsFailed $e) {
            $reminder->status = 'failed';
            $reminder->error = mb_substr($e->getMessage(), 0, 250);
        } catch (Throwable) {
            // Mail transport errors can carry server details; keep the reason generic.
            $reminder->status = 'failed';
            $reminder->error = $channel === 'email'
                ? 'Serwer poczty odrzucił wiadomość lub jest niedostępny.'
                : 'Nieoczekiwany błąd wysyłki SMS.';
        }

        $reminder->save();

        return $reminder;
    }
}
