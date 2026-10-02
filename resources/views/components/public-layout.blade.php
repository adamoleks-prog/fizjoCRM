@props(['title' => 'Zapisy na wizytę'])

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-900">
    <main class="max-w-2xl mx-auto px-4 py-8 space-y-6">
        @if (app(\App\Services\Booking\OnlineBooking::class)->isTestMode())
            <div class="rounded-md bg-amber-100 border border-amber-400 text-amber-900 p-3 text-sm">
                <strong>TRYB TESTOWY</strong> — SMS-y z kodem nie są wysyłane. Kod potwierdzający: <strong class="font-mono">{{ \App\Services\Booking\OnlineBooking::TEST_CODE }}</strong>.
            </div>
        @endif
        {{ $slot }}
    </main>
</body>
</html>
