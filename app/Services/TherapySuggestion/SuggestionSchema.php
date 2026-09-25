<?php

namespace App\Services\TherapySuggestion;

/**
 * The shape of a therapy suggestion, stated twice on purpose: once as a JSON
 * schema the model is asked to follow, once as validation rules we enforce
 * ourselves. Some providers treat the schema as a strong hint rather than a
 * guarantee, so nothing is stored or shown until the rules pass.
 */
class SuggestionSchema
{
    public const VERSION = '1';

    public const STATUSES = ['mozna_zaproponowac_plan', 'wymaga_uzupelnienia_danych', 'wymaga_konsultacji'];

    public const METHOD_ASSESSMENTS = ['wskazana', 'do_rozwazenia', 'niewskazana', 'poza_kompetencjami'];

    public const CONFIDENCE = ['wysoka', 'srednia', 'niska'];

    /**
     * @return array<string, mixed>
     */
    public static function jsonSchema(): array
    {
        $string = ['type' => 'string'];
        $strings = ['type' => 'array', 'items' => $string];

        return self::object([
            'status' => ['type' => 'string', 'enum' => self::STATUSES],
            'podsumowanie_kliniczne' => self::listOf([
                'fakt' => $string,
                'zrodlo' => $string,
            ]),
            'braki_w_danych' => $strings,
            'czerwone_flagi' => self::listOf([
                'opis' => $string,
                'zalecane_dzialanie' => $string,
            ]),
            'cele' => self::object([
                'krotkoterminowe' => $strings,
                'srednioterminowe' => $strings,
                'dlugoterminowe' => $strings,
            ]),
            'plan' => self::listOf([
                'etap' => $string,
                'czas_trwania' => $string,
                'dzialania' => self::listOf([
                    'dzialanie' => $string,
                    'uzasadnienie' => $string,
                    'warunki_bezpieczenstwa' => $string,
                ]),
                'kryteria_progresji' => $strings,
                'kryteria_przerwania' => $strings,
            ]),
            'metody_specjalistyczne' => self::listOf([
                'metoda' => $string,
                'ocena' => ['type' => 'string', 'enum' => self::METHOD_ASSESSMENTS],
                'uzasadnienie' => $string,
            ]),
            'miary_efektu' => self::listOf([
                'miara' => $string,
                'jak_mierzyc' => $string,
            ]),
            'wyjasnienie' => self::listOf([
                'wniosek' => $string,
                'na_podstawie' => $strings,
                'pewnosc' => ['type' => 'string', 'enum' => self::CONFIDENCE],
            ]),
            'pytania_do_terapeuty' => $strings,
        ]);
    }

    /**
     * The same structure as Laravel validation rules. Validated data keeps only the
     * keys listed here, so anything extra the model adds is dropped.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $text = ['required', 'string', 'max:3000'];
        $list = ['present', 'array', 'max:40'];

        return [
            'status' => ['required', 'string', 'in:'.implode(',', self::STATUSES)],

            'podsumowanie_kliniczne' => $list,
            'podsumowanie_kliniczne.*.fakt' => $text,
            'podsumowanie_kliniczne.*.zrodlo' => $text,

            'braki_w_danych' => $list,
            'braki_w_danych.*' => $text,

            'czerwone_flagi' => $list,
            'czerwone_flagi.*.opis' => $text,
            'czerwone_flagi.*.zalecane_dzialanie' => $text,

            'cele' => ['required', 'array'],
            'cele.krotkoterminowe' => $list,
            'cele.krotkoterminowe.*' => $text,
            'cele.srednioterminowe' => $list,
            'cele.srednioterminowe.*' => $text,
            'cele.dlugoterminowe' => $list,
            'cele.dlugoterminowe.*' => $text,

            'plan' => $list,
            'plan.*.etap' => $text,
            'plan.*.czas_trwania' => $text,
            'plan.*.dzialania' => $list,
            'plan.*.dzialania.*.dzialanie' => $text,
            'plan.*.dzialania.*.uzasadnienie' => $text,
            'plan.*.dzialania.*.warunki_bezpieczenstwa' => $text,
            'plan.*.kryteria_progresji' => $list,
            'plan.*.kryteria_progresji.*' => $text,
            'plan.*.kryteria_przerwania' => $list,
            'plan.*.kryteria_przerwania.*' => $text,

            'metody_specjalistyczne' => $list,
            'metody_specjalistyczne.*.metoda' => $text,
            'metody_specjalistyczne.*.ocena' => ['required', 'string', 'in:'.implode(',', self::METHOD_ASSESSMENTS)],
            'metody_specjalistyczne.*.uzasadnienie' => $text,

            'miary_efektu' => $list,
            'miary_efektu.*.miara' => $text,
            'miary_efektu.*.jak_mierzyc' => $text,

            'wyjasnienie' => $list,
            'wyjasnienie.*.wniosek' => $text,
            'wyjasnienie.*.na_podstawie' => $list,
            'wyjasnienie.*.na_podstawie.*' => $text,
            'wyjasnienie.*.pewnosc' => ['required', 'string', 'in:'.implode(',', self::CONFIDENCE)],

            'pytania_do_terapeuty' => $list,
            'pytania_do_terapeuty.*' => $text,
        ];
    }

    /**
     * Strict mode requires every property to be listed as required and nothing
     * else to be allowed.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private static function object(array $properties): array
    {
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private static function listOf(array $properties): array
    {
        return ['type' => 'array', 'items' => self::object($properties)];
    }
}
