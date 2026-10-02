<x-public-layout>
    <h1 class="text-2xl font-semibold">Zapisz się na wizytę</h1>

    @if ($physiotherapists->isEmpty())
        <div class="bg-white rounded-lg shadow-sm p-6">
            Zapisy przez internet są chwilowo niedostępne. Prosimy o kontakt telefoniczny z gabinetem.
        </div>
    @else
        <p class="text-gray-600">Wybierz fizjoterapeutę:</p>
        <div class="space-y-3">
            @foreach ($physiotherapists as $physiotherapist)
                <a href="{{ route('booking.show', $physiotherapist) }}" class="block bg-white rounded-lg shadow-sm p-5 hover:ring-2 hover:ring-indigo-400">
                    <div class="font-semibold text-lg">{{ $physiotherapist->name }}</div>
                    @if ($physiotherapist->practice_name || $physiotherapist->practice_address)
                        <div class="text-sm text-gray-600">{{ collect([$physiotherapist->practice_name, $physiotherapist->practice_address])->filter()->implode(' · ') }}</div>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
</x-public-layout>
