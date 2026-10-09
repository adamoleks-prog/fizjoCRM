<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\Encryption\PatientEraser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PatientErasureController extends Controller
{
    public function store(Request $request, Patient $patient, PatientEraser $eraser): RedirectResponse
    {
        $request->validate(
            ['confirmation' => ['required', 'in:USUŃ'], 'reason_ok' => ['accepted']],
            [
                'confirmation.in' => 'Wpisz USUŃ wielkimi literami, żeby potwierdzić.',
                'confirmation.required' => 'Wpisz USUŃ wielkimi literami, żeby potwierdzić.',
                'reason_ok.accepted' => 'Potwierdź, że okres przechowywania dokumentacji minął albo usunięcie jest uzasadnione.',
            ],
        );

        try {
            $eraser->erase($patient);
        } catch (RuntimeException $e) {
            return back()->withErrors(['confirmation' => $e->getMessage()]);
        }

        return redirect()->route('patients.index')->with('status', 'Dane pacjenta zostały trwale usunięte.');
    }
}
