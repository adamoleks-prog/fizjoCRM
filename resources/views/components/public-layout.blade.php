@props(['title' => 'Zapisy na wizytę'])

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} — FIZJOroom</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-brand-paper text-brand-ink">
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <a href="{{ route('booking.index') }}" class="block"><img src="{{ asset('images/logo.png') }}" alt="FIZJOroom" class="h-10 w-auto"></a>
        @if (app(\App\Services\Booking\OnlineBooking::class)->isTestMode())
            <div class="rounded-md bg-amber-100 border border-amber-400 text-amber-900 p-3 text-sm">
                <strong>TRYB TESTOWY</strong> — SMS-y z kodem nie są wysyłane. Kod potwierdzający: <strong class="font-mono">{{ \App\Services\Booking\OnlineBooking::TEST_CODE }}</strong>.
            </div>
        @endif
        {{ $slot }}
    </main>
</body>
</html>
