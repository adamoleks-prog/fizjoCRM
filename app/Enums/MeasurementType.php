<?php

namespace App\Enums;

enum MeasurementType: string
{
    case Boolean = 'boolean';
    case Scale = 'scale';
    case Numeric = 'numeric';
    case Bilateral = 'bilateral';

    public function label(): string
    {
        return match ($this) {
            self::Boolean => 'Tak / Nie',
            self::Scale => 'Skala 0-10',
            self::Numeric => 'Wartość liczbowa',
            self::Bilateral => 'Wartość liczbowa — strona lewa i prawa',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Boolean => 'np. objaw Lasègue’a dodatni',
            self::Scale => 'np. poziom bólu w skali 0-10',
            self::Numeric => 'np. obwód uda w cm, siła chwytu w kg',
            self::Bilateral => 'np. zakres zgięcia kolana osobno dla lewej i prawej strony',
        };
    }

    /** Only numeric measurements carry a unit. */
    public function usesUnit(): bool
    {
        return in_array($this, [self::Numeric, self::Bilateral], true);
    }

    public function isBilateral(): bool
    {
        return $this === self::Bilateral;
    }
}
