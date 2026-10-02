<x-public-layout title="Odwołanie wizyty">
    <h1 class="text-2xl font-semibold">Wizyta {{ $appointment->starts_at->format('d.m.Y, H:i') }}</h1>
    <p class="text-gray-600">{{ $physiotherapist->name }}{{ $physiotherapist->practice_name ? ', '.$physiotherapist->practice_name : '' }}</p>

    <div class="bg-white rounded-lg shadow-sm p-5 space-y-3">
        @if ($appointment->status === \App\Enums\AppointmentStatus::Cancelled)
            <p><strong>Wizyta jest odwołana.</strong> Dziękujemy za informację.</p>
            <a href="{{ route('booking.show', $physiotherapist) }}" class="inline-block text-indigo-600 underline">Umów nowy termin</a>
        @elseif ($canCancel)
            <p>Nie możesz przyjść? Odwołaj wizytę — termin przyda się komuś innemu.</p>
            <form method="POST" action="{{ route('booking.cancel.confirm', $token) }}">
                @csrf
                <button class="w-full py-3 rounded-md bg-red-600 text-white font-semibold hover:bg-red-500">Odwołuję wizytę</button>
            </form>
        @else
            <p>Tej wizyty nie można już odwołać przez internet.
                @if ($physiotherapist->practice_phone)
                    Zadzwoń do gabinetu: <strong>{{ $physiotherapist->practice_phone }}</strong>.
                @endif
            </p>
        @endif
    </div>
</x-public-layout>
