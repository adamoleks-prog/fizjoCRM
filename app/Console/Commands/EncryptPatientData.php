<?php

namespace App\Console\Commands;

use App\Models\AiClinicalCase;
use App\Models\AiRecommendation;
use App\Models\Appointment;
use App\Models\Concerns\EncryptsPatientData;
use App\Models\Document;
use App\Models\DocumentAnonymization;
use App\Models\Measurement;
use App\Models\PainPoint;
use App\Models\Patient;
use App\Models\PatientComorbidity;
use App\Models\TherapyCycle;
use App\Models\TherapyMilestone;
use App\Services\Encryption\PatientCipher;
use App\Services\Encryption\PatientDataErased;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Encrypts what was written before encryption was introduced: text columns and
 * the PDF files. Safe to run again — encrypted values and files are skipped.
 * Writes directly, so timestamps and calendar sync are not touched.
 */
class EncryptPatientData extends Command
{
    protected $signature = 'patient-data:encrypt {--dry-run : Tylko policz, nic nie zmieniaj}';

    protected $description = 'Szyfruje kluczami pacjentów dane zapisane przed wprowadzeniem szyfrowania';

    /** @var list<class-string<Model>> */
    private const MODELS = [
        Patient::class, Appointment::class, Document::class, DocumentAnonymization::class,
        AiClinicalCase::class, AiRecommendation::class, Measurement::class, PainPoint::class,
        PatientComorbidity::class, TherapyMilestone::class, TherapyCycle::class,
    ];

    public function handle(PatientCipher $cipher): int
    {
        $dry = (bool) $this->option('dry-run');

        foreach (self::MODELS as $class) {
            $this->line(class_basename($class).': '.$this->encryptColumns($class, $cipher, $dry));
        }

        $this->line('Pliki dokumentów: '.$this->encryptFiles($cipher, $dry));
        $this->line('Zapisy online w toku: '.$this->encryptBookingPayloads($dry));

        $this->info($dry ? 'Próba zakończona — nic nie zmieniono.' : 'Gotowe.');

        return self::SUCCESS;
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function encryptColumns(string $class, PatientCipher $cipher, bool $dry): int
    {
        /** @var Model&EncryptsPatientData $prototype */
        $prototype = new $class;
        $columns = $prototype->patientEncrypted();
        $count = 0;

        DB::table($prototype->getTable())->orderBy('id')->chunkById(200, function ($rows) use ($class, $columns, $cipher, $dry, &$count) {
            foreach ($rows as $row) {
                $plain = array_filter(
                    array_intersect_key((array) $row, array_flip($columns)),
                    fn ($value) => is_string($value) && $value !== '' && ! PatientCipher::isEncrypted($value),
                );

                if ($plain === []) {
                    continue;
                }

                $count++;

                if ($dry) {
                    continue;
                }

                // Only the foreign keys are needed to find the patient — no events.
                $model = (new $class)->setRawAttributes((array) $row);
                $patientId = $model->encryptionPatientId();

                if ($patientId === null) {
                    $this->warn(class_basename($class)." #{$row->id}: brak pacjenta, pominięto.");

                    continue;
                }

                try {
                    DB::table($model->getTable())->where('id', $row->id)
                        ->update(array_map(fn (string $value) => $cipher->encrypt($patientId, $value), $plain));
                } catch (PatientDataErased) {
                    // Erased patient — nothing of theirs should be readable anyway.
                    DB::table($model->getTable())->where('id', $row->id)->update(array_map(fn () => null, $plain));
                }
            }
        });

        return $count;
    }

    private function encryptFiles(PatientCipher $cipher, bool $dry): int
    {
        $disk = Storage::disk('patient_documents');
        $count = 0;

        DB::table('documents')->orderBy('id')->select(['id', 'patient_id', 'disk_path'])->chunkById(100, function ($rows) use ($disk, $cipher, $dry, &$count) {
            foreach ($rows as $row) {
                if (! $disk->exists($row->disk_path)) {
                    continue;
                }

                $contents = (string) $disk->get($row->disk_path);

                if (str_starts_with($contents, PatientCipher::FILE_MAGIC)) {
                    continue;
                }

                $count++;

                if (! $dry) {
                    try {
                        $disk->put($row->disk_path, $cipher->encryptFile((int) $row->patient_id, $contents));
                    } catch (PatientDataErased) {
                        $disk->delete($row->disk_path);
                    }
                }
            }
        });

        return $count;
    }

    private function encryptBookingPayloads(bool $dry): int
    {
        $rows = DB::table('booking_verifications')->where('payload', 'like', '{%')->get(['id', 'payload']);

        if (! $dry) {
            foreach ($rows as $row) {
                DB::table('booking_verifications')->where('id', $row->id)->update(['payload' => Crypt::encryptString($row->payload)]);
            }
        }

        return $rows->count();
    }
}
