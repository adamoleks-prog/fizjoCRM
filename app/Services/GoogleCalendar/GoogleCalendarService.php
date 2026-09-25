<?php

namespace App\Services\GoogleCalendar;

use App\Models\Appointment;
use App\Models\GoogleCalendarToken;
use App\Models\User;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Exception as GoogleServiceException;

class GoogleCalendarService implements CalendarSynchronizer
{
    /** The calendar-list scope only reads which calendars exist, so one can be picked. */
    public const SCOPES = [
        Calendar::CALENDAR_EVENTS,
        'https://www.googleapis.com/auth/calendar.calendarlist.readonly',
    ];

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

        $this->deleteFrom($token, $token->google_calendar_id, $appointment->google_event_id);
    }

    public function deleteFrom(GoogleCalendarToken $token, string $calendarId, string $eventId): void
    {
        $service = new Calendar($this->authorizedClient($token));
        $service->events->delete($calendarId, $eventId);
    }

    /**
     * Calendars the user may add events to, primary first. Null when the stored
     * connection predates the calendar-list permission and must be renewed.
     *
     * @return array<int, array{id: string, name: string, primary: bool}>|null
     */
    public function writableCalendars(GoogleCalendarToken $token): ?array
    {
        try {
            $items = (new Calendar($this->authorizedClient($token)))
                ->calendarList->listCalendarList(['minAccessRole' => 'writer'])
                ->getItems();
        } catch (GoogleServiceException $e) {
            if ($e->getCode() === 403) {
                return null;
            }

            throw $e;
        }

        return collect($items)
            ->map(fn ($c) => ['id' => $c->getId(), 'name' => $c->getSummaryOverride() ?: $c->getSummary(), 'primary' => (bool) $c->getPrimary()])
            ->sortBy([fn ($a, $b) => $b['primary'] <=> $a['primary'], fn ($a, $b) => strcmp($a['name'], $b['name'])])
            ->values()
            ->all();
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
            // Name and time only. Treatment notes are health data and stay in the
            // CRM — Google Calendar is not the place for them.
            'summary' => "Wizyta: {$patient->last_name} {$patient->first_name}",
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
