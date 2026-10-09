<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SecurityEventType;
use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use App\Services\Monitoring\SystemStatus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SecurityLogController extends Controller
{
    public function index(Request $request, SystemStatus $status): View
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(SecurityEventType::class)],
            'severity' => ['nullable', 'in:info,warning,critical'],
            'ip' => ['nullable', 'string', 'max:45'],
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $events = SecurityEvent::with('user')
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['severity'] ?? null, fn ($q, $severity) => $q->where('severity', $severity))
            ->when($filters['ip'] ?? null, fn ($q, $ip) => $q->where('ip_address', $ip))
            ->when($filters['q'] ?? null, fn ($q, $text) => $q->where(fn ($q) => $q
                ->where('email', 'like', '%'.$text.'%')
                ->orWhere('path', 'like', '%'.$text.'%')))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<', now()->parse($to)->addDay()))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.security-log', [
            'events' => $events,
            'filters' => $filters,
            'summary' => $status->securitySummary(24),
            'types' => SecurityEventType::cases(),
        ]);
    }
}
