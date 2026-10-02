<?php

namespace App\Services\Booking;

use App\Services\Messaging\AppSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Google reCAPTCHA v3 on the public booking form — invisible to people, it
 * scores each submission and turns away the ones that look automated.
 * Off until the admin enters both keys and switches it on.
 */
class Recaptcha
{
    public const ACTION = 'booking';

    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct(private readonly AppSettings $settings) {}

    public function isActive(): bool
    {
        return $this->settings->bool('recaptcha.enabled')
            && $this->settings->has('recaptcha.site_key')
            && $this->settings->has('recaptcha.secret_key');
    }

    public function siteKey(): ?string
    {
        return $this->settings->get('recaptcha.site_key');
    }

    public function minScore(): float
    {
        return (float) $this->settings->get('recaptcha.min_score', '0.5');
    }

    /**
     * True when Google confirms the token was issued for this site and action
     * with a good enough score. Fails closed: no answer from Google means no.
     */
    public function passes(?string $token, ?string $ip): bool
    {
        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(10)->post(self::VERIFY_URL, [
                'secret' => $this->settings->get('recaptcha.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]);
        } catch (ConnectionException) {
            return false;
        }

        return $response->successful()
            && $response->json('success') === true
            && $response->json('action') === self::ACTION
            && (float) $response->json('score', 0) >= $this->minScore();
    }
}
