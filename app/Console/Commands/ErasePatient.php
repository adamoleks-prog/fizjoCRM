<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Services\Encryption\PatientEraser;
use Illuminate\Console\Command;
use RuntimeException;

/** For patients already moved to the bin, which have no page in the panel. */
class ErasePatient extends Command
{
    protected $signature = 'patient:erase {id : Numer pacjenta}';

    protected $description = 'Trwale usuwa dane pacjenta (niszczy jego klucz szyfrujący)';

    public function handle(PatientEraser $eraser): int
    {
        $patient = Patient::withoutGlobalScopes()->find($this->argument('id'));

        if (! $patient) {
            $this->error('Nie ma takiego pacjenta.');

            return self::FAILURE;
        }

        if (! $this->confirm("Trwale usunąć dane pacjenta #{$patient->id} {$patient->first_name} {$patient->last_name}? Tego nie da się cofnąć.")) {
            return self::FAILURE;
        }

        try {
            $eraser->erase($patient);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Usunięto.');

        return self::SUCCESS;
    }
}
