<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = Appointment::query()
            ->with('patient')
            ->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])
            ->orderBy('starts_at')
            ->get();

        return view('dashboard', [
            'todayAppointments' => $today,
            'upcomingCount' => Appointment::query()
                ->where('starts_at', '>', now())
                ->where('status', AppointmentStatus::Scheduled)
                ->count(),
            'patientCount' => Patient::count(),
            // The coming week after today, grouped by day on the page.
            'upcoming' => Appointment::query()
                ->with(['patient', 'service'])
                ->whereIn('status', [AppointmentStatus::Scheduled, AppointmentStatus::Pending])
                ->whereBetween('starts_at', [now()->addDay()->startOfDay(), now()->addDays(7)->endOfDay()])
                ->orderBy('starts_at')
                ->get()
                ->groupBy(fn (Appointment $a) => $a->starts_at->toDateString()),
            'pendingBookings' => Appointment::query()
                ->with('patient')
                ->where('status', AppointmentStatus::Pending)
                ->where('starts_at', '>', now())
                ->orderBy('starts_at')
                ->get(),
        ]);
    }
}
