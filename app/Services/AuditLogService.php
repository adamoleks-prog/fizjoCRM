<?php

namespace App\Services;

use App\Enums\PatientAccessAction;
use App\Models\Patient;
use App\Models\PatientAccessLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public static function log(Patient $patient, PatientAccessAction $action): void
    {
        PatientAccessLog::create([
            'patient_id' => $patient->id,
            'user_id' => Auth::id(),
            'action' => $action,
            'ip_address' => Request::ip(),
        ]);
    }
}
