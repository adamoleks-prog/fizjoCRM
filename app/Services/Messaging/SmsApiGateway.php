<?php

namespace App\Services\Messaging;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * SMSAPI.pl. Polish characters are normalised by the gateway (normalize=1):
 * with diacritics a message part holds 70 characters instead of 160, which
 * would double the cost of most reminders.
 */
class SmsApiGateway implements SmsGateway
{
    private const URL = 'https://api.smsapi.pl/sms.do';

    public function __construct(private readonly AppSettings $settings) {}

    public function isConfigured(): bool
    {
        return $this->settings->has('sms.token');
    }

    public function send(string $phone, string $message): void
    {
        if (! $this->isConfigured()) {
            throw new MessagingNotConfigured('Bramka SMS nie jest skonfigurowana (Ustawienia wysyłki).');
        }

        $number = PhoneNumber::normalize($phone);

        if ($number === null) {
            throw new SmsFailed('Niepoprawny numer telefonu.');
        }

        $params = [
            'to' => $number,
            'message' => $message,
            'format' => 'json',
            'encoding' => 'utf-8',
            'normalize' => 1,
        ];

        if ($this->settings->has('sms.sender')) {
            $params['from'] = $this->settings->get('sms.sender');
        }

        try {
            $response = Http::withToken($this->settings->get('sms.token'))
                ->asForm()
                ->timeout(20)
                ->post(self::URL, $params);
        } catch (ConnectionException) {
            throw new SmsFailed('Brak połączenia z SMSAPI.');
        }

        // SMSAPI reports most errors with HTTP 200 and an "error" code in the body.
        if ($response->failed() || $response->json('error')) {
            $code = $response->json('error') ?? $response->status();
            $text = $response->json('message');

            throw new SmsFailed('SMSAPI odrzuciło wiadomość (kod '.$code.')'.(is_string($text) ? ': '.mb_substr($text, 0, 150) : '').'.');
        }
    }
}
