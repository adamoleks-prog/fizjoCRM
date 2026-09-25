<!DOCTYPE html>
<html lang="pl">
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; line-height: 1.5; max-width: 560px;">
    <p>Dzień dobry,</p>

    <p>
        przypominamy o wizycie{{ filled($physiotherapist->practice_name) ? ' w '.$physiotherapist->practice_name : '' }}
        <strong>{{ $when }}</strong>.
    </p>

    @if (filled($physiotherapist->practice_address))
        <p>Adres: {{ $physiotherapist->practice_address }}</p>
    @endif

    <p>
        Jeśli nie możesz przyjść, daj nam znać
        @if (filled($physiotherapist->practice_phone))
            pod numerem <strong>{{ $physiotherapist->practice_phone }}</strong>
        @endif
        — zwolniony termin przyda się komuś innemu.
    </p>

    <p>Do zobaczenia,<br>{{ $physiotherapist->name }}</p>

    <p style="font-size: 12px; color: #6b7280;">Wiadomość wysłana automatycznie. Nie odpowiadaj na nią, jeśli chcesz odwołać wizytę — zadzwoń.</p>
</body>
</html>
