<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Zaplanowana',
            self::Completed => 'Odbyta',
            self::Cancelled => 'Odwołana',
            self::NoShow => 'Nieobecność',
        };
    }
}
