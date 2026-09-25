@php
    $r = $recommendation->response ?? [];
    $methodLabels = ['wskazana' => 'Wskazana', 'do_rozwazenia' => 'Do rozważenia', 'niewskazana' => 'Niewskazana', 'poza_kompetencjami' => 'Poza kompetencjami'];
    $confidenceLabels = ['wysoka' => 'pewność wysoka', 'srednia' => 'pewność średnia', 'niska' => 'pewność niska'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Podpowiedź terapii — {{ $recommendation->created_at->format('d.m.Y H:i') }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6 text-gray-900 dark:text-gray-100">

            <a href="{{ route('therapy-cycles.show', $cycle) }}"
               class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                &larr; Asystent terapii: {{ $cycle->name }}
            </a>

            @if (session('status'))
                <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 rounded-md p-3 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="rounded-md border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-900/20 p-4 text-sm text-indigo-900 dark:text-indigo-200">
                <strong>Podpowiedź do oceny przez fizjoterapeutę.</strong>
                Została wygenerowana automatycznie na podstawie spseudonimizowanych danych, bez badania pacjenta. Może zawierać błędy. Decyzję o terapii podejmujesz Ty — nic z tej strony nie trafia do dokumentacji, dopóki sam tego nie przeniesiesz.
            </div>

            @if ($recommendation->isQueued())
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-sm"
                     x-data
                     x-init="const poll = setInterval(async () => {
                         const res = await fetch('{{ route('recommendations.show', $recommendation) }}', { headers: { Accept: 'application/json' } });
                         if (res.ok && (await res.json()).status !== 'queued') { clearInterval(poll); location.reload(); }
                     }, 4000)">
                    Podpowiedź jest przygotowywana. Zwykle trwa to do minuty — strona odświeży się sama.
                </div>
            @elseif ($recommendation->isFailed())
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md p-4 text-sm text-red-800 dark:text-red-300">
                    <strong>Nie udało się przygotować podpowiedzi.</strong> {{ $recommendation->failure_reason }}
                    <br>Zapytanie nie jest ponawiane automatycznie — możesz zlecić je ponownie na stronie asystenta.
                </div>
            @else
                @if ($r['status'] === 'wymaga_konsultacji')
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-300 dark:border-red-700 rounded-md p-4 text-sm text-red-800 dark:text-red-300">
                        <strong>Wymaga konsultacji.</strong> Asystent wskazał czerwone flagi lub ryzyka, które należy wyjaśnić przed terapią.
                    </div>
                @elseif ($r['status'] === 'wymaga_uzupelnienia_danych')
                    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 rounded-md p-4 text-sm text-amber-900 dark:text-amber-200">
                        <strong>Brakuje danych.</strong> Asystent uznał, że bez uzupełnienia dokumentacji plan byłby zgadywaniem.
                    </div>
                @endif

                @if ($r['czerwone_flagi'] !== [])
                    <section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                        <h3 class="font-semibold text-red-700 dark:text-red-400">Czerwone flagi i ryzyka</h3>
                        <ul class="mt-3 space-y-2 text-sm">
                            @foreach ($r['czerwone_flagi'] as $flag)
                                <li><strong>{{ $flag['opis'] }}</strong><br><span class="text-gray-600 dark:text-gray-400">{{ $flag['zalecane_dzialanie'] }}</span></li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold">Podsumowanie kliniczne</h3>
                    <ul class="mt-3 space-y-1 text-sm">
                        @foreach ($r['podsumowanie_kliniczne'] as $item)
                            <li>{{ $item['fakt'] }} <span class="text-xs text-gray-500 dark:text-gray-400">— {{ $item['zrodlo'] }}</span></li>
                        @endforeach
                    </ul>

                    @if ($r['braki_w_danych'] !== [])
                        <h4 class="mt-4 text-sm font-semibold text-amber-800 dark:text-amber-300">Braki w danych</h4>
                        <ul class="mt-1 list-disc list-inside text-sm">
                            @foreach ($r['braki_w_danych'] as $gap)
                                <li>{{ $gap }}</li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold">Cele</h3>
                    <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        @foreach (['krotkoterminowe' => 'Krótkoterminowe', 'srednioterminowe' => 'Średnioterminowe', 'dlugoterminowe' => 'Długoterminowe'] as $key => $label)
                            <div>
                                <h4 class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $label }}</h4>
                                <ul class="mt-1 list-disc list-inside">
                                    @forelse ($r['cele'][$key] as $goal)
                                        <li>{{ $goal }}</li>
                                    @empty
                                        <li class="list-none text-gray-400">—</li>
                                    @endforelse
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold">Plan etapami</h3>
                    <div class="mt-3 space-y-5">
                        @foreach ($r['plan'] as $stage)
                            <div class="border-l-2 border-indigo-300 dark:border-indigo-700 pl-4">
                                <h4 class="font-medium">{{ $stage['etap'] }} <span class="text-sm font-normal text-gray-500 dark:text-gray-400">· {{ $stage['czas_trwania'] }}</span></h4>
                                <ul class="mt-2 space-y-2 text-sm">
                                    @foreach ($stage['dzialania'] as $action)
                                        <li>
                                            <strong>{{ $action['dzialanie'] }}</strong>
                                            <div class="text-gray-600 dark:text-gray-400">{{ $action['uzasadnienie'] }}</div>
                                            <div class="text-xs text-amber-800 dark:text-amber-300">Bezpieczeństwo: {{ $action['warunki_bezpieczenstwa'] }}</div>
                                        </li>
                                    @endforeach
                                </ul>
                                <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                                    <div>
                                        <span class="text-gray-500 dark:text-gray-400">Przejście dalej, gdy:</span>
                                        <ul class="list-disc list-inside">@foreach ($stage['kryteria_progresji'] as $c)<li>{{ $c }}</li>@endforeach</ul>
                                    </div>
                                    <div>
                                        <span class="text-gray-500 dark:text-gray-400">Przerwij, gdy:</span>
                                        <ul class="list-disc list-inside">@foreach ($stage['kryteria_przerwania'] as $c)<li>{{ $c }}</li>@endforeach</ul>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                @if ($r['metody_specjalistyczne'] !== [])
                    <section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                        <h3 class="font-semibold">Metody specjalistyczne</h3>
                        <ul class="mt-3 space-y-2 text-sm">
                            @foreach ($r['metody_specjalistyczne'] as $method)
                                <li>
                                    <strong>{{ $method['metoda'] }}</strong>
                                    <span class="ml-1 text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700">{{ $methodLabels[$method['ocena']] }}</span>
                                    <div class="text-gray-600 dark:text-gray-400">{{ $method['uzasadnienie'] }}</div>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($r['miary_efektu'] !== [])
                    <section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                        <h3 class="font-semibold">Jak mierzyć efekt</h3>
                        <ul class="mt-3 space-y-1 text-sm">
                            @foreach ($r['miary_efektu'] as $measure)
                                <li><strong>{{ $measure['miara'] }}</strong> — {{ $measure['jak_mierzyc'] }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold">Skąd te wnioski</h3>
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ($r['wyjasnienie'] as $why)
                            <li>
                                {{ $why['wniosek'] }}
                                <span class="text-xs text-gray-500 dark:text-gray-400">({{ $confidenceLabels[$why['pewnosc']] }})</span>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Na podstawie: {{ implode('; ', $why['na_podstawie']) }}</div>
                            </li>
                        @endforeach
                    </ul>
                </section>

                @if ($r['pytania_do_terapeuty'] !== [])
                    <section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                        <h3 class="font-semibold">Pytania do Ciebie</h3>
                        <ul class="mt-2 list-disc list-inside text-sm">
                            @foreach ($r['pytania_do_terapeuty'] as $question)
                                <li>{{ $question }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- The physiotherapist's verdict --}}
                <section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold">Twoja ocena</h3>
                    @if ($recommendation->decision !== 'pending')
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ $recommendation->decision === 'useful' ? 'Przydatna' : 'Odrzucona' }}
                            — {{ $recommendation->decidedBy?->name }}, {{ $recommendation->decided_at->format('d.m.Y H:i') }}.
                            @if ($recommendation->decision_note)
                                <br>{{ $recommendation->decision_note }}
                            @endif
                        </p>
                    @endif
                    <form method="POST" action="{{ route('recommendations.decide', $recommendation) }}" class="mt-3 space-y-3">
                        @csrf
                        @method('PATCH')
                        <textarea name="decision_note" rows="2" placeholder="Notatka (opcjonalnie): co było trafne, co nie"
                                  class="block w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">{{ old('decision_note', $recommendation->decision_note) }}</textarea>
                        <div class="flex gap-3">
                            <x-primary-button name="decision" value="useful">Przydatna</x-primary-button>
                            <x-secondary-button type="submit" name="decision" value="rejected">Odrzucam</x-secondary-button>
                        </div>
                    </form>
                </section>
            @endif

            <p class="text-xs text-gray-500 dark:text-gray-400">
                Model: {{ $recommendation->model }}@if ($recommendation->provider) · dostawca: {{ $recommendation->provider }}@endif
                · wersja promptu {{ $recommendation->prompt_version }}
                @if ($recommendation->prompt_tokens) · tokeny: {{ $recommendation->prompt_tokens }} + {{ $recommendation->completion_tokens }} @endif
                @if ($recommendation->cost !== null) · koszt: ${{ number_format((float) $recommendation->cost, 4) }} @endif
                · zlecił(a): {{ $recommendation->requestedBy?->name }}
            </p>
        </div>
    </div>
</x-app-layout>
