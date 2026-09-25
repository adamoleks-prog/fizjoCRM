<?php

namespace App\Services\Messaging;

use App\Models\Appointment;
use App\Models\User;

/**
 * The reminder wording. It names the practice, the time and how to cancel —
 * never the reason for the visit: an SMS passes through the phone operator and
 * may be read by anyone holding the phone.
 */
class ReminderMessage
{
    private const DAYS = ['niedziela', 'poniedziałek', 'wtorek', 'środa', 'czwartek', 'piątek', 'sobota'];

    public static function sms(Appointment $appointment, User $physiotherapist): string
    {
        $parts = ['Przypomnienie: wizyta'.self::where($physiotherapist).' '.self::when($appointment).'.'];

        if (filled($physiotherapist->practice_address)) {
            $parts[] = trim($physiotherapist->practice_address).'.';
        }

        if (filled($physiotherapist->practice_phone)) {
            $parts[] = 'Odwołanie: tel. '.trim($physiotherapist->practice_phone).'.';
        }

        return implode(' ', $parts);
    }

    public static function subject(Appointment $appointment, User $physiotherapist): string
    {
        return 'Przypomnienie o wizycie '.self::when($appointment);
    }

    public static function when(Appointment $appointment): string
    {
        $start = $appointment->starts_at;

        $day = match (true) {
            $start->isToday() => 'dziś',
            $start->isTomorrow() => 'jutro',
            default => self::DAYS[$start->dayOfWeek],
        };

        return $day.' '.$start->format('d.m').' o '.$start->format('H:i');
    }

    private static function where(User $physiotherapist): string
    {
        return filled($physiotherapist->practice_name) ? ' w '.trim($physiotherapist->practice_name) : '';
    }
}
