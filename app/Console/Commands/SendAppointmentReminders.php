<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Services\Messaging\AppSettings;
use App\Services\Messaging\ReminderSender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendAppointmentReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Wysyła przypomnienia o wizytach przypadających w najbliższych godzinach';

    /** Too close to the visit for a reminder to be of use — and likely booked just now. */
    private const MIN_HOURS_AHEAD = 2;

    public function handle(AppSettings $settings, ReminderSender $sender): int
    {
        if (! $settings->bool('reminders.sms_enabled') && ! $settings->bool('reminders.email_enabled')) {
            $this->info('Przypomnienia są wyłączone.');

            return self::SUCCESS;
        }

        $hoursBefore = max(self::MIN_HOURS_AHEAD + 1, (int) $settings->get('reminders.hours_before', 24));

        $appointments = Appointment::query()
            ->where('status', AppointmentStatus::Scheduled)
            ->whereNull('reminder_sent_at')
            ->whereBetween('starts_at', [now()->addHours(self::MIN_HOURS_AHEAD), now()->addHours($hoursBefore)])
            ->whereHas('patient', fn ($q) => $q->where('reminders_enabled', true))
            ->orderBy('starts_at')
            ->get();

        $sent = 0;

        foreach ($appointments as $appointment) {
            // Claimed before sending, so two overlapping runs never remind twice.
            $claimed = DB::table('appointments')
                ->where('id', $appointment->id)
                ->whereNull('reminder_sent_at')
                ->update(['reminder_sent_at' => now()]);

            if ($claimed === 0) {
                continue;
            }

            $sent += count($sender->send($appointment));
        }

        $this->info("Wysłano/próbowano: {$sent} (wizyt: {$appointments->count()}).");

        return self::SUCCESS;
    }
}
