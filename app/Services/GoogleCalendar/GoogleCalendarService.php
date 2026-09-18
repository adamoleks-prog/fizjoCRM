<?php

namespace App\Services\GoogleCalendar;

use App\Models\Appointment;
use App\Models\GoogleCalendarToken;
use App\Models\User;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;

class GoogleCalendarService implements CalendarSynchronizer
{
    public const SCOPES = [Calendar::CALENDAR_EVENTS];

    public function client(): Client
    {
        $client = new Client;
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect_uri'));
        $client->setScopes(self::SCOPES);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }

    public function upsertEvent(Appointment $appointment): ?string
    {
        $token = $this->tokenFor($appointment->operator);

        if (! $token) {
            return null;
        }

        $service = new Calendar($this->authorizedClient($token));
        $event = $this->buildEvent($appointment);

        $saved = $appointment->google_event_id
            ? $service->events->update($token->google_calendar_id, $appointment->google_event_id, $event)
            : $service->events->insert($token->google_calendar_id, $event);

        return $saved->getId();
    }

    public function deleteEvent(Appointment $appointment): void
    {
        $token = $this->tokenFor($appointment->operator);

        if (! $token || ! $appointment->google_event_id) {
            return;
        }

        $service = new Calendar($this->authorizedClient($token));
        $service->events->delete($token->google_calendar_id, $appointment->google_event_id);
    }

    /**
     * Refreshes proactively on expiry so every call site gets a valid token
     * without repeating the check or reacting to a 401.
     */
    private function authorizedClient(GoogleCalendarToken $token): Client
    {
        $client = $this->client();

        if ($token->isExpired()) {
            $client->refreshToken($token->refresh_token);
            $new = $client->getAccessToken();

            $token->update([
                'access_token' => $new['access_token'],
                'expires_at' => now()->addSeconds($new['expires_in'] ?? 3600),
            ]);
        }

        $client->setAccessToken($token->access_token);

        return $client;
    }

    private function tokenFor(User $operator): ?GoogleCalendarToken
    {
        return $operator->googleCalendarToken;
    }

    private function buildEvent(Appointment $appointment): Event
    {
        $patient = $appointment->patient;

        return new Event([
            'summary' => "Wizyta: {$patient->last_name} {$patient->first_name}",
            'description' => $appointment->treatment_notes,
            'start' => new EventDateTime([
                'dateTime' => $appointment->starts_at->toRfc3339String(),
                'timeZone' => config('app.timezone'),
            ]),
            'end' => new EventDateTime([
                'dateTime' => $appointment->ends_at->toRfc3339String(),
                'timeZone' => config('app.timezone'),
            ]),
        ]);
    }
}
