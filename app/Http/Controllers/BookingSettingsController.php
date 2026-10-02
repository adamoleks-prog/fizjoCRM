<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Services\Booking\OnlineBooking;
use App\Services\Messaging\SmsGateway;
use App\Services\WorkSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Each physiotherapist decides whether patients may book them online and on
 * what terms. Off by default.
 */
class BookingSettingsController extends Controller
{
    public function edit(Request $request, WorkSchedule $schedule, SmsGateway $sms): View
    {
        $user = $request->user();

        return view('booking.settings', [
            'user' => $user,
            'slotMinutes' => $schedule->slotMinutes($user->id),
            'hasSchedule' => $schedule->hasOwnPattern($user->id),
            'smsReady' => $sms->isConfigured(),
            'testMode' => app(OnlineBooking::class)->isTestMode(),
            'requiresSmsCode' => app(OnlineBooking::class)->requiresSmsCode(),
            'pending' => Appointment::query()
                ->with('patient')
                ->where('operator_id', $user->id)
                ->where('status', AppointmentStatus::Pending)
                ->orderBy('starts_at')
                ->get(),
        ]);
    }

    public function update(Request $request, WorkSchedule $schedule): RedirectResponse
    {
        $slot = $schedule->slotMinutes($request->user()->id);
        $duration = ['nullable', 'integer', 'min:'.$slot, 'max:240', 'multiple_of:'.$slot];

        $data = $request->validate([
            'online_booking_enabled' => ['boolean'],
            'booking_visit_minutes' => $duration,
            'booking_first_visit_minutes' => $duration,
            'booking_min_notice_hours' => ['required', 'integer', 'between:0,168'],
            'booking_days_ahead' => ['required', 'integer', 'between:1,120'],
            'booking_cancel_hours' => ['required', 'integer', 'between:0,168'],
        ], [
            'booking_visit_minutes.multiple_of' => "Czas wizyty musi być wielokrotnością slotu ({$slot} min).",
            'booking_first_visit_minutes.multiple_of' => "Czas pierwszej wizyty musi być wielokrotnością slotu ({$slot} min).",
        ]);

        $request->user()->forceFill([...$data, 'online_booking_enabled' => $request->boolean('online_booking_enabled')])->save();

        return redirect()->route('booking.settings')->with('status', 'Zapisano ustawienia zapisów online.');
    }

    public function approve(Request $request, Appointment $appointment, OnlineBooking $booking): RedirectResponse
    {
        $this->authorize('update', $appointment);
        abort_unless($appointment->status === AppointmentStatus::Pending, 422);

        $updatePhone = $request->boolean('update_phone');
        $booking->approve($appointment, $updatePhone);

        return back()->with('status', 'Wizyta potwierdzona. Pacjent dostał SMS.'.($updatePhone ? ' Numer telefonu w karcie zaktualizowany.' : ''));
    }

    /** "That is someone else" — a new card from the booking form, visit moved and confirmed. */
    public function approveAsNewPatient(Appointment $appointment, OnlineBooking $booking): RedirectResponse
    {
        $this->authorize('update', $appointment);
        abort_unless($appointment->status === AppointmentStatus::Pending, 422);

        $patient = $booking->approveAsNewPatient($appointment);

        return redirect()
            ->route('appointments.show', $appointment)
            ->with('status', 'Założono nową kartę: '.$patient->first_name.' '.$patient->last_name.'. Wizyta przeniesiona i potwierdzona, pacjent dostał SMS.');
    }

    public function reject(Appointment $appointment, OnlineBooking $booking): RedirectResponse
    {
        $this->authorize('update', $appointment);
        abort_unless($appointment->status === AppointmentStatus::Pending, 422);

        $booking->reject($appointment);

        return back()->with('status', 'Wizyta odrzucona, termin zwolniony. Pacjent dostał SMS.');
    }
}
