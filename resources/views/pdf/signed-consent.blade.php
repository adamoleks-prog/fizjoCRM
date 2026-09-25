<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 22mm 18mm 22mm 18mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10.5pt; color: #111827; line-height: 1.5; }
        .practice { font-size: 9pt; color: #4b5563; border-bottom: 1px solid #d1d5db; padding-bottom: 6px; margin-bottom: 16px; }
        h1 { font-size: 14pt; margin: 0 0 14px; }
        .text { white-space: pre-line; text-align: justify; }
        .signature { margin-top: 28px; width: 60%; margin-left: 40%; text-align: center; }
        .signature img { max-width: 100%; height: 90px; }
        .signature .line { border-top: 1px solid #111827; padding-top: 3px; font-size: 9pt; }
        .meta { margin-top: 26px; font-size: 7.5pt; color: #6b7280; border-top: 1px dashed #d1d5db; padding-top: 6px; }
    </style>
</head>
<body>
    <div class="practice">
        {{ $physiotherapist->practice_name ?: $physiotherapist->name }}
        {{ $physiotherapist->practice_address ? ' · '.$physiotherapist->practice_address : '' }}
    </div>

    <h1>{{ $title }}</h1>

    <div class="text">{{ $text }}</div>

    <div class="signature">
        <img src="{{ $signature }}" alt="Podpis">
        <div class="line">{{ $patient->first_name }} {{ $patient->last_name }}, {{ $signedAt->format('d.m.Y H:i') }}</div>
    </div>

    <div class="meta">
        Podpisano elektronicznie na urządzeniu w gabinecie, w obecności: {{ $witness->name }}.
        Data i godzina: {{ $signedAt->format('d.m.Y H:i:s') }}. Skrót SHA-256 treści: {{ $hash }}
    </div>
</body>
</html>
