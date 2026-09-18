<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\TherapyCycle;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(Icd10CodeSeeder::class);

        User::factory()->create([
            'name' => 'Administrator',
            'email' => 'admin@crmfizjo.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
        ]);

        $operator = User::factory()->create([
            'name' => 'Anna Kowalska',
            'email' => 'operator@crmfizjo.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Operator,
        ]);

        $patients = Patient::factory()->count(8)->forOperator($operator)->create();

        $slotMinutes = (int) config('appointments.slot_minutes');
        [$hour, $minute] = explode(':', config('appointments.working_hours.start'));
        $weekStart = now()->startOfWeek()->setTime((int) $hour, (int) $minute);

        $diagnoses = ['M54.5', 'M75.1', 'M17', 'M54.3', 'M77.1', 'G56.0', 'M25.5', 'M51'];

        foreach ($patients as $index => $patient) {
            $startsAt = $weekStart->copy()
                ->addDays(intdiv($index, 2))
                ->addMinutes(($index % 2) * $slotMinutes * 4);

            $cycle = $startsAt->isPast()
                ? TherapyCycle::factory()->forPatient($patient)->create([
                    'name' => 'Rehabilitacja — '.$patient->last_name,
                ])
                : null;

            Appointment::factory()->forOperator($operator)->create([
                'patient_id' => $patient->id,
                'therapy_cycle_id' => $cycle?->id,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addMinutes($slotMinutes),
                'status' => $startsAt->isPast() ? AppointmentStatus::Completed : AppointmentStatus::Scheduled,
                'icd10_code' => $startsAt->isPast() ? $diagnoses[$index % count($diagnoses)] : null,
                'procedures' => $startsAt->isPast() ? 'Terapia manualna, kinesiotaping' : null,
            ]);
        }
    }
}
