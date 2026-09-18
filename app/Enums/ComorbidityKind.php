<?php

namespace App\Enums;

enum ComorbidityKind: string
{
    case Active = 'active';
    case Chronic = 'chronic';
    case Past = 'past';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktywne',
            self::Chronic => 'Przewlekłe',
            self::Past => 'Przebyte',
        };
    }

    /** Past conditions matter less day to day, so they sort last. */
    public function sortOrder(): int
    {
        return match ($this) {
            self::Active => 0,
            self::Chronic => 1,
            self::Past => 2,
        };
    }
}
