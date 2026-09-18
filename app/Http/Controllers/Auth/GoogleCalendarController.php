<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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

        return redirect()
            ->route('google-calendar.edit')
            ->with('status', 'Kalendarz Google został połączony.');
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
}
