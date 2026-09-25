<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Asystent terapii: {{ $cycle->name }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <a href="{{ route('patients.show', $cycle->patient_id) }}"
               class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                &larr; Karta pacjenta: {{ $cycle->patient->last_name }} {{ $cycle->patient->first_name }}
            </a>

            @if (session('status'))
                <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 rounded-md p-3 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-300 rounded-md p-3 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            @unless ($enabled)
                <div class="bg-gray-50 dark:bg-gray-900/40 border border-gray-200 dark:border-gray-700 rounded-md p-4 text-sm text-gray-700 dark:text-gray-300">
                    <strong>Wysyłanie do asystenta jest wyłączone.</strong>
                    Funkcja zostanie włączona po podpisaniu umowy powierzenia danych. Dane cyklu możesz już przygotować i przejrzeć — nic nie opuści serwera.
                </div>
            @endunless

            {{-- Step 1: the case --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                <h3 class="font-semibold">1. Dane do wysłania</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Odbyte wizyty w cyklu: {{ $completedVisits }}. Do danych trafiają wywiad, badania, wnioski, zabiegi, notatki, pomiary, mapa dolegliwości, plan terapii, choroby współistniejące i zatwierdzone dokumenty — po usunięciu danych osobowych. Nazwa cyklu i tytuły dokumentów nie są wysyłane.
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-3 text-sm">
                    @if (! $case)
                        <span class="px-2 py-1 rounded bg-gray-100 dark:bg-gray-700">Nieprzygotowane</span>
                    @elseif ($stale)
                        <span class="px-2 py-1 rounded bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300">Nieaktualne — dokumentacja zmieniła się od przygotowania</span>
                    @elseif ($case->isApproved())
                        <span class="px-2 py-1 rounded bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300">
                            Zatwierdzone ({{ $case->approvedBy?->name }}, {{ $case->approved_at->format('d.m.Y H:i') }})
                        </span>
                    @else
                        <span class="px-2 py-1 rounded bg-amber-100 dark:bg-amber-900/40 text-amber-900 dark:text-amber-200">Wersja robocza — czeka na przegląd</span>
                    @endif

                    @if ($case)
                        <a href="{{ route('clinical-cases.show', $cycle) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Przejrzyj</a>
                    @endif

                    @if (! $case || $stale)
                        <form method="POST" action="{{ route('clinical-cases.store', $cycle) }}">
                            @csrf
                            <x-primary-button>{{ $case ? 'Przygotuj od nowa' : 'Przygotuj dane' }}</x-primary-button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Step 2: ask --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                <h3 class="font-semibold">2. Podpowiedź planu terapii</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Wysyłany jest wyłącznie zatwierdzony tekst, do modelu {{ config('services.openrouter.model') }} przetwarzanego w UE, bez przechowywania danych przez dostawcę. Podpowiedź to materiał do Twojej oceny — nic nie trafia automatycznie do dokumentacji.
                    Metody specjalistyczne są dobierane do <a href="{{ route('profile.edit') }}" class="underline">profilu kompetencji</a>.
                </p>

                @php($ready = $enabled && $case && ! $stale && $case->isApproved() && ! $hasQueued)
                <form method="POST" action="{{ route('recommendations.store', $cycle) }}" class="mt-4">
                    @csrf
                    <x-primary-button :disabled="! $ready">Poproś o podpowiedź</x-primary-button>
                    @if ($hasQueued)
                        <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">Poprzednia podpowiedź jest jeszcze przygotowywana.</span>
                    @endif
                </form>
            </div>

            {{-- History --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                <h3 class="font-semibold">Dotychczasowe podpowiedzi</h3>

                @if ($recommendations->isEmpty())
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Brak.</p>
                @else
                    <table class="mt-3 w-full text-sm">
                        <thead class="text-left text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="py-1 font-normal">Data</th>
                                <th class="py-1 font-normal">Stan</th>
                                <th class="py-1 font-normal">Ocena</th>
                                <th class="py-1 font-normal text-right">Koszt</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($recommendations as $recommendation)
                                <tr>
                                    <td class="py-2">
                                        <a href="{{ route('recommendations.show', $recommendation) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                            {{ $recommendation->created_at->format('d.m.Y H:i') }}
                                        </a>
                                    </td>
                                    <td class="py-2">
                                        @include('therapy-cycles._recommendation-status', ['recommendation' => $recommendation])
                                    </td>
                                    <td class="py-2">
                                        {{ ['pending' => '—', 'useful' => 'Przydatna', 'rejected' => 'Odrzucona'][$recommendation->decision] }}
                                    </td>
                                    <td class="py-2 text-right tabular-nums">
                                        {{ $recommendation->cost !== null ? '$'.number_format((float) $recommendation->cost, 4) : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
