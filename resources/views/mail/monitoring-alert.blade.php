<!DOCTYPE html>
<html lang="pl">
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; line-height: 1.5; max-width: 640px;">
    <h2 style="font-size: 18px; color: #b91c1c;">{{ $alertSubject }}</h2>

    <ul style="padding-left: 18px;">
        @foreach ($lines as $line)
            <li>{{ $line }}</li>
        @endforeach
    </ul>

    <p><a href="{{ $link }}" style="color: #00a2e6;">Otwórz w panelu</a></p>

    <p style="font-size: 12px; color: #6b7280;">
        Alert wysłany automatycznie z panelu {{ config('app.name') }}. Ten sam rodzaj alertu przychodzi najwyżej raz na godzinę.
        Alerty wyłączysz w panelu: Stan systemu.
    </p>
</body>
</html>
