<?php

namespace App\Services\Encryption;

use RuntimeException;

/**
 * Text and files encrypted with a patient's key. Encrypted values carry a
 * prefix, so a value written before encryption was introduced is recognised
 * and still read (until patient-data:encrypt converts it).
 */
class PatientCipher
{
    public const PREFIX = 'pk1:';

    /** Start of an encrypted file — a PDF starts with "%PDF", never with this. */
    public const FILE_MAGIC = "FZENC1\n";

    public function __construct(private readonly PatientKeyring $keys) {}

    public static function isEncrypted(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }

    public function encrypt(int $patientId, string $plain): string
    {
        return self::PREFIX.$this->keys->for($patientId)->encryptString($plain);
    }

    /**
     * @return string|null null when the patient's data was erased
     */
    public function decrypt(int $patientId, string $stored): ?string
    {
        if (! self::isEncrypted($stored)) {
            return $stored;
        }

        $encrypter = $this->keys->existing($patientId);

        if ($encrypter === null) {
            if ($this->keys->isErased($patientId)) {
                return null;
            }

            // A missing key for a living patient (e.g. a database restored from
            // before an erasure) — the administrator is alerted, the page still
            // opens with the field empty.
            report(new RuntimeException("Brak klucza szyfrującego pacjenta #{$patientId}."));

            return null;
        }

        return $encrypter->decryptString(substr($stored, strlen(self::PREFIX)));
    }

    public function encryptFile(int $patientId, string $contents): string
    {
        return self::FILE_MAGIC.$this->keys->for($patientId)->encryptString($contents);
    }

    /**
     * @return string|null null when the patient's data was erased
     */
    public function decryptFile(int $patientId, string $stored): ?string
    {
        if (! str_starts_with($stored, self::FILE_MAGIC)) {
            return $stored;
        }

        return $this->decrypt($patientId, self::PREFIX.substr($stored, strlen(self::FILE_MAGIC)));
    }
}
