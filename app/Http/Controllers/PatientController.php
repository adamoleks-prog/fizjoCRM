<?php

namespace App\Http\Controllers;

use App\Enums\PatientAccessAction;
use App\Enums\UserRole;
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Models\Patient;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Patient::class);

        $patients = Patient::query()
            ->with('operator')
            ->when($request->string('search')->trim()->value(), function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->paginate(20)
            ->withQueryString();

        return view('patients.index', compact('patients'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Patient::class);

        return view('patients.create', [
            'operators' => $request->user()->isAdmin()
                ? User::query()->where('role', UserRole::Operator)->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function store(StorePatientRequest $request): RedirectResponse
    {
        $patient = new Patient($request->safe()->except('operator_id'));
        $patient->operator_id = $this->resolveOperatorId($request);
        $patient->save();

        return redirect()
            ->route('patients.show', $patient)
            ->with('status', 'Pacjent został dodany.');
    }

    public function show(Patient $patient): View
    {
        $this->authorize('view', $patient);

        AuditLogService::log($patient, PatientAccessAction::Viewed);

        $patient->load(['operator', 'documents']);
        $appointments = $patient->appointments()->orderByDesc('starts_at')->get();

        return view('patients.show', compact('patient', 'appointments'));
    }

    public function edit(Patient $patient): View
    {
        $this->authorize('update', $patient);

        return view('patients.edit', compact('patient'));
    }

    public function update(UpdatePatientRequest $request, Patient $patient): RedirectResponse
    {
        $patient->update($request->validated());

        AuditLogService::log($patient, PatientAccessAction::Updated);

        return redirect()
            ->route('patients.show', $patient)
            ->with('status', 'Dane pacjenta zostały zaktualizowane.');
    }

    public function destroy(Patient $patient): RedirectResponse
    {
        $this->authorize('delete', $patient);

        $patient->delete();

        return redirect()
            ->route('patients.index')
            ->with('status', 'Pacjent został usunięty.');
    }

    private function resolveOperatorId(StorePatientRequest $request): int
    {
        $user = $request->user();

        if ($user->isAdmin() && $request->filled('operator_id')) {
            return (int) $request->integer('operator_id');
        }

        return $user->id;
    }
}
