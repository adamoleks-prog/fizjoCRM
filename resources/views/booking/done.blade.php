<x-public-layout title="Zapisano">
    @if ($appointment->status === \App\Enums\AppointmentStatus::Pending)
        <h1 class="text-2xl font-semibold">Prośba o wizytę wysłana</h1>
        <div class="bg-white rounded-lg shadow-sm p-5 space-y-2">
            <p>Termin <strong>{{ $appointment->starts_at->format('d.m.Y, H:i') }}</strong> jest zarezerwowany dla Ciebie.</p>
            <p>Ponieważ to Twoja pierwsza wizyta, fizjoterapeuta potwierdzi ją SMS-em — zwykle w ciągu jednego dnia roboczego.</p>
        </div>
    @else
        <h1 class="text-2xl font-semibold">Wizyta umówiona</h1>
        <div class="bg-white rounded-lg shadow-sm p-5 space-y-2">
            <p><strong>{{ $appointment->starts_at->format('d.m.Y, H:i') }}</strong> — {{ $physiotherapist->name }}{{ $physiotherapist->practice_name ? ', '.$physiotherapist->practice_name : '' }}</p>
            @if ($physiotherapist->practice_address)
                <p class="text-gray-600">{{ $physiotherapist->practice_address }}</p>
            @endif
            <p class="text-sm text-gray-600">Potwierdzenie wysłaliśmy SMS-em. Jest w nim link do odwołania wizyty.</p>
        </div>
    @endif
</x-public-layout>
