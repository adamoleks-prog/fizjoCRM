<?php

namespace App\Enums;

enum BodyView: string
{
    case Front = 'front';
    case Back = 'back';

    public function label(): string
    {
        return match ($this) {
            self::Front => 'Przód',
            self::Back => 'Tył',
        };
    }
}
