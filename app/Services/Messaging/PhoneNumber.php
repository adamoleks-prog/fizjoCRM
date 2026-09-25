<?php

namespace App\Services\Messaging;

class PhoneNumber
{
    /**
     * International form without "+", as SMS gateways expect it. A bare nine-digit
     * number is taken to be Polish.
     */
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 9) {
            $digits = '48'.$digits;
        }

        return strlen($digits) >= 11 && strlen($digits) <= 15 ? $digits : null;
    }
}
