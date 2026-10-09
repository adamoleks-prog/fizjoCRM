<?php

namespace App\Services\Encryption;

use App\Enums\AppointmentStatus;
use App\Enums\SecurityEventType;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\Monitoring\SecurityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Permanent erasure of a patient ("right to be forgotten"). The patient's key
 * is destroyed, so every encrypted note, diagnosis, document text and file is
 * unreadable from that moment — and in backups once the key backups made
 * before the erasure have expired. Name and contact details are overwritten,
 * the files are deleted and visits are removed from Google Calendar.
 *
 * Medical records have a legal retention period (20 years in Poland) — the
 * caller is responsible for erasing only when that no longer applies or the
 * patient's request is otherwise justified.
 */
class PatientEraser
{
    public function __construct(
        private readonly PatientKeyring $keys,
        private readonly SecurityLog $log,
    ) {}

    /**
     * @throws RuntimeException when the patient still has upcoming visits
     */
    public function erase(Patient $patient): void
    {
        if ($patient->erased_at !== null) {
            return;
        }

        $upcoming = Appointment::withoutGlobalScopes()
            ->where('patient_id', $patient->id)
            ->whereNull('deleted_at')
            ->whereIn('status', [AppointmentStatus::Scheduled->value, AppointmentStatus::Pending->value])
            ->where('starts_at', '>', now())
            ->exists();

        if ($upcoming) {
            throw new RuntimeException('Pacjent ma zaplanowane wizyty — najpierw je odwołaj albo usuń.');
        }

        $paths = DB::table('documents')->where('patient_id', $patient->id)->pluck('disk_path');

        DB::transaction(function () use ($patient) {
            // Through the model, so the calendar events are removed as well.
            Appointment::withoutGlobalScopes()->where('patient_id', $patient->id)->whereNull('deleted_at')
                ->get()->each->delete();

            DB::table('appointments')->where('patient_id', $patient->id)
                ->update(['booking_phone' => null, 'cancel_token' => null]);

            DB::table('patients')->where('id', $patient->id)->update([
                'first_name' => 'Usunięty',
                'last_name' => 'pacjent #'.$patient->id,
                'phone' => null,
                'email' => null,
                'address' => null,
                'notes' => null,
                'date_of_birth' => null,
                'reminders_enabled' => false,
                'erased_at' => now(),
                'deleted_at' => $patient->deleted_at ?? now(),
                'updated_at' => now(),
            ]);

            $this->keys->destroy($patient->id);
        });

        foreach ($paths as $path) {
            Storage::disk('patient_documents')->delete($path);
        }

        $this->log->record(SecurityEventType::PatientErased, ['patient_id' => $patient->id]);
    }
}
