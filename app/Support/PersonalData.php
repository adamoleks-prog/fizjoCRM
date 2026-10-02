<?php

namespace App\Support;

use Closure;

/**
 * One set of rules for a person's name, phone and e-mail, used by the public
 * booking form and by the patient form in the panel alike.
 */
class PersonalData
{
    /** Letters only — Polish ones included. */
    public const FIRST_NAME = '/^\p{L}+$/u';

    /** Letters, optionally two parts joined by a hyphen (Nowak-Kowalska). */
    public const LAST_NAME = '/^\p{L}+(-\p{L}+)?$/u';

    /** Something@domain.tld — the plain "email" rule lets "jan@gmail" through. */
    public const EMAIL = '/^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$/';

    /**
     * @return array<int, mixed>
     */
    public static function firstName(): array
    {
        return ['required', 'string', 'max:40', 'regex:'.self::FIRST_NAME];
    }

    /**
     * @return array<int, mixed>
     */
    public static function lastName(): array
    {
        return ['required', 'string', 'max:60', 'regex:'.self::LAST_NAME];
    }

    /**
     * @return array<int, mixed>
     */
    public static function email(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'max:255', 'email:rfc', 'regex:'.self::EMAIL];
    }

    /**
     * Nine digits of a Polish number (the +48 is fixed in the form). A mobile is
     * required where texts go out; obviously made-up numbers are refused.
     *
     * @return array<int, mixed>
     */
    public static function phone(bool $required, bool $mobileOnly): array
    {
        return [$required ? 'required' : 'nullable', 'string', function (string $attribute, mixed $value, Closure $fail) use ($mobileOnly) {
            $value = (string) $value;

            if (! preg_match('/^[0-9]{9}$/', $value)) {
                $fail('Podaj 9 cyfr numeru telefonu — bez spacji i bez +48.');
            } elseif (count(array_unique(str_split($value))) === 1 || str_starts_with($value, '0')) {
                $fail('To nie wygląda na prawdziwy numer telefonu.');
            } elseif ($mobileOnly && ! preg_match('/^[4-8]/', $value)) {
                $fail('Podaj numer telefonu komórkowego — na niego wyślemy SMS.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'first_name.regex' => 'Imię może zawierać tylko litery — bez cyfr, spacji i znaków specjalnych.',
            'last_name.regex' => 'Nazwisko może zawierać tylko litery (dwuczłonowe połącz myślnikiem, np. Nowak-Kowalska).',
            'email.email' => 'Podaj poprawny adres e-mail, np. jan.kowalski@gmail.com.',
            'email.regex' => 'Podaj poprawny adres e-mail, np. jan.kowalski@gmail.com.',
        ];
    }

    /**
     * What the user typed, reduced to the nine national digits when it is a
     * Polish number in any common form (+48 602 118 940, 0048602118940, …).
     */
    public static function nationalDigits(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '0048')) {
            $digits = substr($digits, 4);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '48')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }
}
