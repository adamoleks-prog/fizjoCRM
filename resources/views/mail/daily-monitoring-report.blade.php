@php
    $colors = ['ok' => '#047857', 'warning' => '#b45309', 'error' => '#b91c1c'];
    $marks = ['ok' => '✓', 'warning' => '!', 'error' => '✗'];
@endphp
<!DOCTYPE html>
<html lang="pl">
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; line-height: 1.5; max-width: 680px;">
    <h2 style="font-size: 18px;">Raport dzienny — {{ now()->format('d.m.Y') }}</h2>

    <h3 style="font-size: 15px; margin-bottom: 4px;">Stan systemu</h3>
    <table style="border-collapse: collapse; width: 100%; font-size: 14px;">
        @foreach ($checks as $check)
            <tr>
                <td style="padding: 4px 8px 4px 0; color: {{ $colors[$check['state']] }}; font-weight: bold; vertical-align: top;">{{ $marks[$check['state']] }}</td>
                <td style="padding: 4px 8px 4px 0; vertical-align: top; white-space: nowrap;">{{ $check['label'] }}</td>
                <td style="padding: 4px 0; vertical-align: top; color: #4b5563;">{{ $check['detail'] }}</td>
            </tr>
        @endforeach
    </table>

    <h3 style="font-size: 15px; margin-bottom: 4px;">Bezpieczeństwo — ostatnie 24 h ({{ $security['total'] }} zdarzeń)</h3>
    @if ($security['total'] === 0)
        <p style="font-size: 14px;">Brak zdarzeń.</p>
    @else
        <ul style="font-size: 14px; padding-left: 18px;">
            @foreach ($security['byType'] as $row)
                <li>{{ $row->type->label() }}: {{ $row->total }}</li>
            @endforeach
        </ul>
        @if ($security['topIps']->isNotEmpty())
            <p style="font-size: 14px; margin-bottom: 2px;">Najaktywniejsze adresy IP (ostrzeżenia i zdarzenia krytyczne):</p>
            <ul style="font-size: 14px; padding-left: 18px;">
                @foreach ($security['topIps'] as $row)
                    <li>{{ $row->ip_address }} — {{ $row->total }}</li>
                @endforeach
            </ul>
        @endif
    @endif

    @if ($appErrors->isNotEmpty())
        <h3 style="font-size: 15px; margin-bottom: 4px;">Błędy aplikacji — ostatnie 24 h</h3>
        <ul style="font-size: 14px; padding-left: 18px;">
            @foreach ($appErrors as $error)
                <li>{{ class_basename($error->exception) }} — {{ $error->file }}:{{ $error->line }} (×{{ $error->occurrences }})</li>
            @endforeach
        </ul>
    @endif

    <p><a href="{{ route('admin.system.show') }}" style="color: #00a2e6;">Stan systemu</a> · <a href="{{ route('admin.security.index') }}" style="color: #00a2e6;">Dziennik bezpieczeństwa</a></p>

    <p style="font-size: 12px; color: #6b7280;">Raport wysyłany codziennie o 7:00. Wyłączysz go w panelu: Stan systemu.</p>
</body>
</html>
