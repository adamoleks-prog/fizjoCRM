<?php

namespace App\Services\Encryption;

use App\Models\Patient;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * One random data key per patient, stored encrypted with the application key
 * (APP_KEY). Clinical text and files of a patient are encrypted with that
 * patient's key; deleting it ("erasure") makes all of them unreadable for good,
 * including copies in older backups once the short-lived key backups expire.
 *
 * Keys and the patient behind a visit, cycle or document are remembered for
 * the request or queue job (scoped binding), so a page full of visits does not
 * repeat the lookups.
 */
class PatientKeyring
{
    /** @var array<int, Encrypter|null> */
    private array $encrypters = [];

    /** @var array<string, int|null> */
    private array $owners = [];

    /**
     * The patient's key, created on first use.
     *
     * @throws PatientDataErased when the patient's data has been erased
     */
    public function for(int $patientId): Encrypter
    {
        if ($encrypter = $this->existing($patientId)) {
            return $encrypter;
        }

        if ($this->isErased($patientId)) {
            throw new PatientDataErased($patientId);
        }

        $key = random_bytes(32);

        // insertOrIgnore: two requests creating the first key at once keep one.
        DB::table('patient_keys')->insertOrIgnore([
            'patient_id' => $patientId,
            'encrypted_key' => Crypt::encryptString(base64_encode($key)),
            'created_at' => now(),
        ]);

        unset($this->encrypters[$patientId]);

        return $this->existing($patientId) ?? throw new \RuntimeException("Nie udało się utworzyć klucza pacjenta #{$patientId}.");
    }

    public function existing(int $patientId): ?Encrypter
    {
        if (array_key_exists($patientId, $this->encrypters) && $this->encrypters[$patientId] !== null) {
            return $this->encrypters[$patientId];
        }

        $stored = DB::table('patient_keys')->where('patient_id', $patientId)->value('encrypted_key');

        if ($stored === null) {
            return null;
        }

        return $this->encrypters[$patientId] = new Encrypter(
            base64_decode(Crypt::decryptString($stored)),
            config('app.cipher'),
        );
    }

    public function isErased(int $patientId): bool
    {
        return Patient::withoutGlobalScopes()->whereKey($patientId)->whereNotNull('erased_at')->exists();
    }

    /** Deletes the key — irreversible. */
    public function destroy(int $patientId): void
    {
        DB::table('patient_keys')->where('patient_id', $patientId)->delete();
        $this->encrypters[$patientId] = null;
    }

    public function patientOfAppointment(?int $id): ?int
    {
        return $this->owner('appointments', $id);
    }

    public function patientOfCycle(?int $id): ?int
    {
        return $this->owner('therapy_cycles', $id);
    }

    public function patientOfDocument(?int $id): ?int
    {
        return $this->owner('documents', $id);
    }

    /** After a visit was moved to another patient. */
    public function forgetAppointment(int $id): void
    {
        unset($this->owners['appointments:'.$id]);
    }

    private function owner(string $table, ?int $id): ?int
    {
        if ($id === null) {
            return null;
        }

        $key = $table.':'.$id;

        if (! array_key_exists($key, $this->owners)) {
            $patientId = DB::table($table)->where('id', $id)->value('patient_id');
            $this->owners[$key] = $patientId === null ? null : (int) $patientId;
        }

        return $this->owners[$key];
    }
}
