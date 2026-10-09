<?php

namespace App\Http\Controllers;

use App\Enums\SecurityEventType;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\Monitoring\SecurityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, SecurityLog $log): RedirectResponse
    {
        $request->user()->fill($request->validated());

        $oldEmail = $request->user()->getOriginal('email');
        $emailChanged = $request->user()->isDirty('email');

        if ($emailChanged) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        // The login e-mail is the key to the account — changing it is how a takeover sticks.
        if ($emailChanged) {
            $log->record(SecurityEventType::EmailChanged, ['previous' => $oldEmail], email: $request->user()->email);
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }
}
