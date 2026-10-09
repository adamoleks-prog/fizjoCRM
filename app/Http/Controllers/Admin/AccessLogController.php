<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PatientAccessAction;
use App\Http\Controllers\Controller;
use App\Models\PatientAccessLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Who opened which patient's records, downloaded documents, sent data to the
 * assistant. Answers a patient's RODO question "who has seen my data".
 */
class AccessLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'action' => ['nullable', Rule::enum(PatientAccessAction::class)],
            'patient' => ['nullable', 'string', 'max:100'],
            'patient_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $logs = PatientAccessLog::query()
            ->with([
                'user',
                'patient' => fn ($q) => $q->withoutGlobalScopes(),
            ])
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('action', $action))
            ->when($filters['patient_id'] ?? null, fn ($q, $id) => $q->where('patient_id', $id))
            ->when($filters['patient'] ?? null, fn ($q, $text) => $q->whereIn('patient_id', function ($sub) use ($text) {
                $sub->select('id')->from('patients');
                // "Jan Kowalski" — every word has to match the first or the last name.
                foreach (preg_split('/\s+/', trim($text)) as $word) {
                    $sub->where(fn ($w) => $w->where('last_name', 'like', '%'.$word.'%')->orWhere('first_name', 'like', '%'.$word.'%'));
                }
            }))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<', now()->parse($to)->addDay()))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.access-log', [
            'logs' => $logs,
            'filters' => $filters,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'actions' => PatientAccessAction::cases(),
        ]);
    }
}
