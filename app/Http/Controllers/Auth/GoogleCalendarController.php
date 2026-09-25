<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SyncAppointmentToGoogleCalendar;
use App\Models\Appointment;
use App\Models\User;
use App\Services\GoogleCalendar\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoogleCalendarController extends Controller
{
    public function __construct(private readonly GoogleCalendarService $service) {}

    public function edit(Request $request): View
    {
        return view('settings.google-calendar', [
            'connected' => $request->user()->googleCalendarToken !== null,
            'configured' => filled(config('services.google.client_id')),
            'upcoming' => $this->upcoming($request->user())->count(),
        ]);
    }

    public function redirect(): RedirectResponse
    {
        return redirect()->away($this->service->client()->createAuthUrl());
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()
                ->route('google-calendar.edit')
                ->with('status', 'Połączenie z Google Calendar zostało anulowane.');
        }

        $client = $this->service->client();
        $token = $client->fetchAccessTokenWithAuthCode($request->string('code')->value());

        if (isset($token['error'])) {
            return redirect()
                ->route('google-calendar.edit')
                ->withErrors(['google' => 'Nie udało się połączyć z Google Calendar.']);
        }

        $user = $request->user();

        $user->googleCalendarToken()->updateOrCreate([], [
            'access_token' => $token['access_token'],
            // Google only returns a refresh token on first consent; keep the stored one otherwise.
            'refresh_token' => $token['refresh_token'] ?? $user->googleCalendarToken?->refresh_token,
            'expires_at' => now()->addSeconds($token['expires_in'] ?? 3600),
        ]);

        $user->forceFill(['google_calendar_connected_at' => now()])->save();

        // Visits booked before the calendar was connected would otherwise only
        // appear after their next edit.
        $queued = $this->queueUpcoming($user);

        return redirect()
            ->route('google-calendar.edit')
            ->with('status', 'Kalendarz Google został połączony.'.($queued ? " Wysyłam do niego nadchodzące wizyty ({$queued})." : ''));
    }

    /** Sends every upcoming visit again — after connecting, or when something is missing. */
    public function sync(Request $request): RedirectResponse
    {
        abort_unless($request->user()->googleCalendarToken, 404);

        $queued = $this->queueUpcoming($request->user());

        return redirect()
            ->route('google-calendar.edit')
            ->with('status', $queued
                ? "Wysyłam do kalendarza nadchodzące wizyty ({$queued}). Pojawią się w ciągu minuty–dwóch."
                : 'Nie masz nadchodzących wizyt jako fizjoterapeuta prowadzący.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->googleCalendarToken()->delete();
        $user->forceFill(['google_calendar_connected_at' => null])->save();

        return redirect()
            ->route('google-calendar.edit')
            ->with('status', 'Kalendarz Google został odłączony.');
    }

    /**
     * Visits land in the calendar of the physiotherapist who runs them — not of
     * whoever booked them.
     */
    private function upcoming(User $user)
    {
        return Appointment::withoutGlobalScopes()
            ->where('operator_id', $user->id)
            ->where('status', AppointmentStatus::Scheduled)
            ->where('starts_at', '>=', now()->startOfDay());
    }

    private function queueUpcoming(User $user): int
    {
        $ids = $this->upcoming($user)->pluck('id');

        foreach ($ids as $id) {
            SyncAppointmentToGoogleCalendar::dispatch($id);
        }

        return $ids->count();
    }
}
