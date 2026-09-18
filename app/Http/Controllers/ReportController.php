<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function monthly(Request $request): View
    {
        $this->authorize('viewAny', Appointment::class);

        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->string('month')->value())->startOfMonth()
            : Carbon::now()->startOfMonth();

        $operatorId = $request->user()->isAdmin() ? $request->integer('operator_id') ?: null : $request->user()->id;

        $appointments = Appointment::query()
            ->with(['patient', 'operator'])
            ->whereBetween('starts_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->when($operatorId, fn ($query) => $query->where('operator_id', $operatorId))
            ->orderBy('starts_at')
            ->get();

        return view('reports.monthly', [
            'month' => $month,
            'appointments' => $appointments,
            'countsByStatus' => $appointments->countBy(fn (Appointment $a) => $a->status->value),
            'statuses' => AppointmentStatus::cases(),
            'operators' => $request->user()->isAdmin()
                ? User::query()->where('role', UserRole::Operator)->orderBy('name')->get()
                : collect(),
            'selectedOperatorId' => $operatorId,
        ]);
    }
}
