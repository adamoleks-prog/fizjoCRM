<?php

namespace App\Models\Concerns;

use App\Services\Encryption\PatientCipher;
use LogicException;

/**
 * Stores the listed attributes encrypted with the patient's own key.
 *
 * In memory the model always holds plain text: values are decrypted when the
 * model is loaded and encrypted just before writing, then shown as plain text
 * again. Code using the model does not change. Queries that bypass the model
 * (pluck, value, DB::table) see ciphertext — use the model for these columns.
 *
 * A new record whose patient is not known yet (a new patient has no id before
 * the insert) is written without those values first and gets them encrypted
 * right after the insert.
 */
trait EncryptsPatientData
{
    /** @var array<string, string>|null */
    private ?array $pendingEncryption = null;

    private bool $forceEncryption = false;

    /**
     * @return list<string>
     */
    abstract public function patientEncrypted(): array;

    abstract public function encryptionPatientId(): ?int;

    public static function bootEncryptsPatientData(): void
    {
        static::retrieved(fn ($model) => $model->decryptPatientData());
        static::saving(fn ($model) => $model->encryptPatientData());
        static::saved(fn ($model) => $model->decryptPatientData());
        static::created(fn ($model) => $model->encryptPendingPatientData());
    }

    public function decryptPatientData(): void
    {
        $encrypted = array_filter(
            $this->patientEncrypted(),
            fn (string $attribute) => PatientCipher::isEncrypted($this->attributes[$attribute] ?? null),
        );

        if ($encrypted === []) {
            return;
        }

        $patientId = $this->encryptionPatientId()
            ?? throw new LogicException(static::class.': nie wiadomo, czyj klucz odszyfrowuje dane (brak kolumny pacjenta w zapytaniu?).');

        $cipher = app(PatientCipher::class);

        foreach ($encrypted as $attribute) {
            $this->attributes[$attribute] = $cipher->decrypt($patientId, $this->attributes[$attribute]);
        }

        $this->syncOriginalAttributes($encrypted);
    }

    /**
     * Writes everything again with the current owner's key — for records whose
     * patient changed through their parent (a visit moved to another card).
     */
    public function reencryptPatientData(): void
    {
        $this->forceEncryption = true;
        $this->save();
        $this->forceEncryption = false;
    }

    protected function encryptPatientData(): void
    {
        // Moved to another patient: everything must be under the new key.
        $ownerChanged = $this->exists && collect(['patient_id', 'appointment_id', 'therapy_cycle_id', 'document_id'])
            ->contains(fn (string $column) => array_key_exists($column, $this->attributes) && $this->isDirty($column));
        $all = $ownerChanged || $this->forceEncryption;

        $toEncrypt = array_filter(
            $this->patientEncrypted(),
            fn (string $attribute) => (! $this->exists || $all || $this->isDirty($attribute))
                && is_string($this->attributes[$attribute] ?? null)
                && $this->attributes[$attribute] !== ''
                && ! PatientCipher::isEncrypted($this->attributes[$attribute]),
        );

        if ($toEncrypt === []) {
            return;
        }

        $patientId = $this->encryptionPatientId();

        if ($patientId === null) {
            if ($this->exists) {
                throw new LogicException(static::class.': brak pacjenta, nie da się zaszyfrować danych.');
            }

            // Plain text never reaches the database: insert empty, fill after.
            foreach ($toEncrypt as $attribute) {
                $this->pendingEncryption[$attribute] = $this->attributes[$attribute];
                $this->attributes[$attribute] = null;
            }

            return;
        }

        $cipher = app(PatientCipher::class);

        foreach ($toEncrypt as $attribute) {
            $this->attributes[$attribute] = $cipher->encrypt($patientId, $this->attributes[$attribute]);
        }
    }

    /**
     * JSON columns are compared by their decoded value, and ciphertext does not
     * decode — without this an encrypted array would look unchanged and never
     * be written.
     */
    public function originalIsEquivalent($key)
    {
        if (in_array($key, $this->patientEncrypted(), true) && PatientCipher::isEncrypted($this->attributes[$key] ?? null)) {
            return ($this->original[$key] ?? null) === $this->attributes[$key];
        }

        return parent::originalIsEquivalent($key);
    }

    protected function encryptPendingPatientData(): void
    {
        if (! $this->pendingEncryption) {
            return;
        }

        $patientId = $this->encryptionPatientId()
            ?? throw new LogicException(static::class.': brak pacjenta po zapisie.');

        $cipher = app(PatientCipher::class);
        $stored = array_map(fn (string $plain) => $cipher->encrypt($patientId, $plain), $this->pendingEncryption);

        static::query()->withoutGlobalScopes()->whereKey($this->getKey())->update($stored);

        foreach ($this->pendingEncryption as $attribute => $plain) {
            $this->attributes[$attribute] = $plain;
        }

        $this->syncOriginalAttributes(array_keys($this->pendingEncryption));
        $this->pendingEncryption = null;
    }
}
