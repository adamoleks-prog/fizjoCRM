<!DOCTYPE html>
<html lang="pl">
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; line-height: 1.5; max-width: 560px;">
    <p>Dzień dobry,</p>

    <p>w załączniku przesyłam kartę wizyty z {{ $appointment->starts_at->format('d.m.Y') }} — z wykonanymi zabiegami i zaleceniami do domu.</p>

    <p>Pozdrawiam,<br>{{ $physiotherapist->name }}{{ filled($physiotherapist->practice_name) ? ', '.$physiotherapist->practice_name : '' }}</p>

    @if (filled($physiotherapist->practice_phone))
        <p style="font-size: 13px; color: #4b5563;">Tel. {{ $physiotherapist->practice_phone }}</p>
    @endif
</body>
</html>
