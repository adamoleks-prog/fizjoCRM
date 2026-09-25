<?php

namespace App\Services\Consents;

use App\Models\ConsentTemplate;
use App\Models\User;

/**
 * Starting wordings, created for a physiotherapist who has none yet. They are
 * drafts to adapt — the practice's lawyer or data protection officer should
 * check them before the first real signature.
 */
class DefaultConsentTemplates
{
    public static function ensureFor(User $physiotherapist): void
    {
        if (ConsentTemplate::withTrashed()->where('operator_id', $physiotherapist->id)->exists()) {
            return;
        }

        foreach (self::templates() as $name => $body) {
            $template = new ConsentTemplate(['name' => $name, 'body' => $body]);
            $template->operator_id = $physiotherapist->id;
            $template->save();
        }
    }

    /**
     * @return array<string, string>
     */
    private static function templates(): array
    {
        return [
            'Klauzula informacyjna RODO' => <<<'TXT'
Ja, {PACJENT} (data urodzenia: {DATA_URODZENIA}), potwierdzam, że zapoznałem(-am) się z informacją o przetwarzaniu moich danych osobowych:

1. Administratorem danych jest {GABINET}.
2. Dane osobowe i dane o stanie zdrowia są przetwarzane w celu udzielania świadczeń zdrowotnych i prowadzenia dokumentacji medycznej (art. 6 ust. 1 lit. c oraz art. 9 ust. 2 lit. h RODO), przez okres wymagany przepisami o dokumentacji medycznej.
3. Numer telefonu i adres e-mail są wykorzystywane do przypominania o wizytach i przesyłania zaleceń.
4. Przy planowaniu terapii fizjoterapeuta może korzystać z narzędzia wspomagającego opartego na sztucznej inteligencji. Przekazywane są wyłącznie dane o przebiegu terapii po usunięciu imienia, nazwiska, danych kontaktowych i innych danych identyfikujących, do dostawców przetwarzających dane na terenie Unii Europejskiej, bez ich przechowywania. Decyzje terapeutyczne zawsze podejmuje fizjoterapeuta.
5. Przysługuje mi prawo dostępu do danych, ich sprostowania, ograniczenia przetwarzania, przenoszenia oraz wniesienia skargi do Prezesa Urzędu Ochrony Danych Osobowych.
6. Podanie danych jest niezbędne do udzielenia świadczenia.
TXT,
            'Zgoda na zabiegi fizjoterapeutyczne' => <<<'TXT'
Ja, {PACJENT} (data urodzenia: {DATA_URODZENIA}), wyrażam zgodę na przeprowadzenie badania fizjoterapeutycznego oraz zabiegów fizjoterapeutycznych przez {FIZJOTERAPEUTA} w {GABINET}.

Zostałem(-am) poinformowany(-a) o celu, przebiegu i możliwych skutkach ubocznych proponowanych zabiegów oraz o możliwości zadawania pytań. Wiem, że mogę w każdej chwili wycofać zgodę lub przerwać zabieg.

Oświadczam, że przekazałem(-am) fizjoterapeucie prawdziwe informacje o stanie zdrowia, przyjmowanych lekach, przebytych chorobach i zabiegach oraz że poinformuję go o każdej zmianie.
TXT,
        ];
    }
}
