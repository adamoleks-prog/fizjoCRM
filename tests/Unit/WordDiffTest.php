<?php

use App\Services\Anonymization\WordDiff;

/** @param array<int, array{text: string, changed: bool}> $segments */
function changedTexts(array $segments): array
{
    return array_values(array_map(
        fn (array $s) => $s['text'],
        array_filter($segments, fn (array $s) => $s['changed']),
    ));
}

it('marks nothing when the texts are identical', function () {
    [$a, $b] = WordDiff::segments('Kolano prawe bolesne.', 'Kolano prawe bolesne.');

    expect(changedTexts($a))->toBe([])->and(changedTexts($b))->toBe([]);
});

it('marks a replaced word on both sides', function () {
    [$a, $b] = WordDiff::segments('Pacjent Kowalska zgłasza ból.', 'Pacjent [PACJENT] zgłasza ból.');

    expect(changedTexts($a))->toBe(['Kowalska'])
        ->and(changedTexts($b))->toBe(['[PACJENT]']);
});

it('marks several separate changes on one line', function () {
    [$a, $b] = WordDiff::segments(
        'Anna Kowalska mieszka w Kętach od 2019.',
        '[PACJENT] mieszka w [MIEJSCOWOŚĆ] od 2018.',
    );

    expect(changedTexts($b))->toContain('[PACJENT]')->toContain('[MIEJSCOWOŚĆ]')->toContain('2018.')
        ->and(changedTexts($a))->toContain('Kętach');
});

it('marks a word that was removed without a replacement', function () {
    [$a, $b] = WordDiff::segments('Pacjent zgłasza silny ból.', 'Pacjent zgłasza ból.');

    expect(changedTexts($a))->toBe(['silny'])->and(changedTexts($b))->toBe([]);
});

it('keeps line breaks and compares line by line', function () {
    [$a, $b] = WordDiff::segments("Wywiad: ból.\nPESEL: 85032012348\nZalecenia: ćwiczenia.", "Wywiad: ból.\nPESEL: [PESEL]\nZalecenia: ćwiczenia.");

    $joined = fn (array $s) => implode('', array_column($s, 'text'));

    expect($joined($a))->toBe("Wywiad: ból.\nPESEL: 85032012348\nZalecenia: ćwiczenia.")
        ->and($joined($b))->toBe("Wywiad: ból.\nPESEL: [PESEL]\nZalecenia: ćwiczenia.")
        ->and(changedTexts($a))->toBe(['85032012348'])
        ->and(changedTexts($b))->toBe(['[PESEL]']);
});

it('reproduces both texts exactly from the segments', function () {
    $original = "Pacjent: Anna Kowalska\nTel. 602 118 940\n\nWywiad z  podwójną spacją.";
    $redacted = "Pacjent: [PACJENT]\nTel. [TELEFON]\n\nWywiad z  podwójną spacją.";

    [$a, $b] = WordDiff::segments($original, $redacted);

    expect(implode('', array_column($a, 'text')))->toBe($original)
        ->and(implode('', array_column($b, 'text')))->toBe($redacted);
});

it('shows everything as changed when the line count differs', function () {
    [$a, $b] = WordDiff::segments("jedna\ndwie", 'jedna dwie');

    expect($a)->toHaveCount(1)->and($a[0]['changed'])->toBeTrue()
        ->and($b[0]['changed'])->toBeTrue();
});

it('counts a replaced word once, not twice', function () {
    expect(WordDiff::changedWords('Pacjent Kowalska zgłasza ból.', 'Pacjent [PACJENT] zgłasza ból.'))->toBe(1)
        ->and(WordDiff::changedWords('a b c', 'a b c'))->toBe(0)
        ->and(WordDiff::changedWords('Anna Kowalska mieszka w Kętach.', '[PACJENT] mieszka w [MIEJSCOWOŚĆ].'))->toBe(3);
});

it('handles a long line without exhausting memory', function () {
    $words = array_map(fn ($i) => "słowo{$i}", range(1, 3000));
    $original = implode(' ', $words);

    $changed = $words;
    $changed[1500] = '[USUNIĘTE]';

    expect(WordDiff::changedWords($original, implode(' ', $changed)))->toBe(1);
});
