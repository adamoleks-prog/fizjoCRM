<?php

namespace App\Enums;

enum RedactionCategory: string
{
    case Patient = 'patient';
    case Pesel = 'pesel';
    case Address = 'address';
    case Locality = 'locality';
    case Phone = 'phone';
    case Email = 'email';
    case Date = 'date';
    case IdDocument = 'id_document';
    case Doctor = 'doctor';
    case Facility = 'facility';
    case OtherPerson = 'other_person';

    public function token(): string
    {
        return match ($this) {
            self::Patient => '[PACJENT]',
            self::Pesel => '[PESEL]',
            self::Address => '[ADRES]',
            self::Locality => '[MIEJSCOWOŚĆ]',
            self::Phone => '[TELEFON]',
            self::Email => '[EMAIL]',
            self::Date => '[DATA]',
            self::IdDocument => '[DOKUMENT]',
            self::Doctor => '[LEKARZ]',
            self::Facility => '[PLACÓWKA]',
            self::OtherPerson => '[OSOBA]',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Patient => 'Dane pacjenta',
            self::Pesel => 'PESEL',
            self::Address => 'Adres',
            self::Locality => 'Miejscowość',
            self::Phone => 'Telefon',
            self::Email => 'E-mail',
            self::Date => 'Data',
            self::IdDocument => 'Dokument tożsamości',
            self::Doctor => 'Lekarz',
            self::Facility => 'Placówka',
            self::OtherPerson => 'Osoba trzecia',
        };
    }

    /**
     * Categories where a miss is a data breach rather than an inconvenience —
     * the test suite holds these to zero tolerance.
     */
    public function isCritical(): bool
    {
        return in_array($this, [self::Patient, self::Pesel, self::Phone, self::Email, self::Address], true);
    }
}
