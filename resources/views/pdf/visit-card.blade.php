<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Karta wizyty {{ $appointment->starts_at->format('d.m.Y') }}</title>
    <style>
        @page { margin: 22mm 18mm 20mm 18mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10.5pt; color: #1f2937; line-height: 1.45; }
        .practice { border-bottom: 2px solid #4f46e5; padding-bottom: 8px; margin-bottom: 18px; }
        .practice-name { font-size: 15pt; font-weight: bold; color: #312e81; }
        .practice-contact { font-size: 9pt; color: #4b5563; }
        h1 { font-size: 14pt; margin: 0 0 10px; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.meta td { padding: 3px 0; vertical-align: top; }
        table.meta td.label { width: 32%; color: #6b7280; }
        h2 { font-size: 11pt; color: #312e81; margin: 18px 0 6px; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; }
        .text { white-space: pre-line; }
        .muted { color: #9ca3af; }
        .next { background: #eef2ff; border: 1px solid #c7d2fe; padding: 8px 10px; margin-top: 18px; }
        .footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 8pt; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="practice">
        <div class="practice-name">{{ $physiotherapist->practice_name ?: $physiotherapist->name }}</div>
        <div class="practice-contact">
            {{ collect([$physiotherapist->practice_address, $physiotherapist->practice_phone ? 'tel. '.$physiotherapist->practice_phone : null])->filter()->implode(' · ') }}
        </div>
    </div>

    <h1>Karta wizyty</h1>

    <table class="meta">
        <tr><td class="label">Pacjent</td><td>{{ $patient->first_name }} {{ $patient->last_name }}</td></tr>
        <tr><td class="label">Data wizyty</td><td>{{ $appointment->starts_at->format('d.m.Y, H:i') }}</td></tr>
        <tr><td class="label">Fizjoterapeuta</td><td>{{ $physiotherapist->name }}</td></tr>
        @if ($appointment->icd10_code)
            <tr><td class="label">Rozpoznanie (ICD-10)</td><td>{{ $appointment->icd10_code }}{{ $appointment->icd10Name() ? ' — '.$appointment->icd10Name() : '' }}</td></tr>
        @endif
    </table>

    <h2>Wykonane zabiegi</h2>
    @if (filled($appointment->procedures))
        <div class="text">{{ trim($appointment->procedures) }}</div>
    @else
        <div class="muted">—</div>
    @endif

    <h2>Zalecenia</h2>
    @if (filled($appointment->patient_recommendations))
        <div class="text">{{ trim($appointment->patient_recommendations) }}</div>
    @else
        <div class="muted">Brak zaleceń.</div>
    @endif

    @if ($nextVisits->isNotEmpty())
        <div class="next">
            <strong>{{ $nextVisits->count() === 1 ? 'Następna wizyta' : 'Kolejne wizyty' }}:</strong>
            {{ $nextVisits->map(fn ($v) => $v->starts_at->format('d.m.Y, H:i'))->implode(' · ') }}
            @if ($physiotherapist->practice_phone)
                <br><span style="font-size: 9pt;">Jeśli nie możesz przyjść, zadzwoń: {{ $physiotherapist->practice_phone }}</span>
            @endif
        </div>
    @endif

    <div class="footer">Wygenerowano {{ now()->format('d.m.Y H:i') }} · Dokument zawiera dane o zdrowiu — przechowuj go w bezpiecznym miejscu.</div>
</body>
</html>
