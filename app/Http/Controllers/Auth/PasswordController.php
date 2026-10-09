<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SecurityEventType;
use App\Http\Controllers\Controller;
use App\Services\Monitoring\SecurityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request, SecurityLog $log): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $log->record(SecurityEventType::PasswordChanged, email: $request->user()->email);

        return back()->with('status', 'password-updated');
    }
}
