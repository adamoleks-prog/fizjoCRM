<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Przegląd przed analizą: {{ $document->displayName() }}
        </h2>
    </x-slot>

    <style>
        .rv-pane { white-space: pre-wrap; word-break: break-word; font-family: ui-monospace, "Cascadia Code", Consolas, monospace; font-size: 12.5px; line-height: 1.75; }
        .rv-pane mark { padding: 0 2px; border-radius: 2px; color: inherit; }
        .rv-removed { background: #fee2e2; color: #991b1b; text-decoration: line-through; }
        .rv-token { background: #e0e7ff; color: #3730a3; font-weight: 500; }
        .rv-shifted { background: #e0f2fe; color: #075985; }
        .rv-finding { background: #fde68a; color: #78350f; outline: 1px solid #d97706; }
        @media (prefers-color-scheme: dark) {
            .rv-removed { background: #450a0a; color: #fca5a5; }
            .rv-token { background: #1e1b4b; color: #a5b4fc; }
            .rv-shifted { background: #082f49; color: #7dd3fc; }
            .rv-finding { background: #451a03; color: #fcd34d; outline-color: #d97706; }
        }
    </style>

    <div class="py-10">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <a href="{{ route('patients.show', $document->patient_id) }}"
               class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                &larr; Karta pacjenta: {{ $document->patient->last_name }} {{ $document->patient->first_name }}
            </a>

            @if (session('status'))
                <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 rounded-md p-3 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->has('approval') || $errors->has('acknowledge'))
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-300 rounded-md p-3 text-sm">
                    {{ $errors->first('approval') ?: $errors->first('acknowledge') }}
                </div>
            @endif

            {{-- Status --}}
            @if ($stale)
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md p-4 text-sm text-red-800 dark:text-red-300">
                    <strong>Ta wersja jest nieaktualna.</strong>
                    Dokument został odczytany ponownie lub zmieniły się reguły usuwania danych. Zatwierdzenie jest zablokowane, dopóki nie wygenerujesz wersji od nowa.
                </div>
            @elseif ($record->isApproved())
                <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-md p-4 text-sm text-emerald-800 dark:text-emerald-300 flex flex-wrap items-center justify-between gap-3">
                    <span>
                        <strong>Zatwierdzone do analizy</strong>
                        przez {{ $record->approvedBy?->name ?? 'nieznanego użytkownika' }},
                        {{ $record->approved_at->format('d.m.Y H:i') }}.
                    </span>
                    <form method="POST" action="{{ route('anonymizations.revoke', $document) }}">
                        @csrf
                        @method('DELETE')
                        <button class="underline hover:no-underline">Wycofaj zatwierdzenie</button>
                    </form>
                </div>
            @else
                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-md p-4 text-sm text-amber-900 dark:text-amber-200">
                    <strong>Wersja robocza.</strong>
                    Nic nie zostanie przekazane do analizy, dopóki jej nie zatwierdzisz. To, co jest po prawej, jest jedynym tekstem, który mógłby opuścić serwer.
                </div>
            @endif

            {{-- What was removed --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-5">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">Co zostało usunięte lub zmienione</h3>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Ręcznie poprawionych słów: {{ $record->manual_edits }}
                    </span>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    @forelse ($categories as $category)
                        <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                            {{ $category['label'] }} <strong>{{ $category['count'] }}</strong>
                        </span>
                    @empty
                        <span class="text-sm text-gray-500 dark:text-gray-400">Nic nie znaleziono do usunięcia.</span>
                    @endforelse
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    To pseudonimizacja, nie pełna anonimizacja: rzadkie rozpoznanie w połączeniu z wiekiem może nadal wskazywać osobę, czego żaden filtr nie usunie.
                </p>
            </div>

            {{-- Findings --}}
            @if ($residue['findings'] !== [])
                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 rounded-md p-4">
                    <h3 class="font-semibold text-amber-900 dark:text-amber-200 text-sm">
                        Do sprawdzenia ({{ count($residue['findings']) }})
                    </h3>
                    <p class="mt-1 text-sm text-amber-900 dark:text-amber-200">
                        W tekście po prawej zostały fragmenty, które wyglądają na dane osobowe, ale system nie był pewny, czy je usunąć. Są zaznaczone na żółto.
                    </p>
                    <p class="mt-2 text-sm font-mono text-amber-900 dark:text-amber-200">
                        {{ implode(' · ', $residue['findings']) }}
                    </p>
                </div>
            @endif

            {{-- Comparison --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4" x-data="{ editing: {{ $errors->has('anonymized_text') ? 'true' : 'false' }} }">
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-5 min-w-0">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">
                        Oryginał <span class="normal-case font-normal">— usunięte na czerwono</span>
                    </h3>
                    <div class="rv-pane text-gray-900 dark:text-gray-100">{{ $originalHtml }}</div>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-5 min-w-0">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Do wysłania <span class="normal-case font-normal">— zastąpione na niebiesko</span>
                        </h3>
                        <button type="button" @click="editing = !editing"
                                class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline"
                                x-text="editing ? 'Zamknij edycję' : 'Edytuj ręcznie'"></button>
                    </div>

                    <div class="rv-pane text-gray-900 dark:text-gray-100" x-show="!editing">{{ $outgoingHtml }}</div>

                    <form method="POST" action="{{ route('anonymizations.update', $document) }}" x-show="editing" x-cloak>
                        @csrf
                        @method('PUT')
                        <textarea id="anonymized_text" name="anonymized_text" rows="18"
                                  class="rv-pane block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">{{ old('anonymized_text', $record->anonymized_text) }}</textarea>
                        <x-input-error :messages="$errors->get('anonymized_text')" class="mt-2" />
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Usuń pozostałe dane osobowe albo przywróć słowo, które system usunął niepotrzebnie. Zapis wycofuje wcześniejsze zatwierdzenie, a przed ponownym zatwierdzeniem tekst jest sprawdzany jeszcze raz.
                        </p>
                        <x-primary-button class="mt-3">Zapisz poprawki</x-primary-button>
                    </form>
                </div>
            </div>

            {{-- Approval --}}
            @unless ($record->isApproved())
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-5">
                    <form method="POST" action="{{ route('anonymizations.approve', $document) }}" class="space-y-4">
                        @csrf

                        @if ($residue['findings'] !== [])
                            <label class="flex items-start gap-2 text-sm text-gray-900 dark:text-gray-100">
                                <input type="checkbox" name="acknowledge" value="1"
                                       class="mt-0.5 rounded border-gray-300 dark:border-gray-700 text-indigo-600">
                                <span>Sprawdziłem fragmenty oznaczone na żółto i zdecydowałem, że mogą zostać w tekście.</span>
                            </label>
                        @endif

                        <div class="flex flex-wrap items-center gap-3">
                            <x-primary-button :disabled="$stale">Zatwierdź do analizy</x-primary-button>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                Przed zatwierdzeniem tekst jest sprawdzany jeszcze raz pod kątem PESEL-u, telefonu, e-maila i danych pacjenta.
                            </span>
                        </div>
                    </form>
                </div>
            @endunless

            {{-- Regenerate --}}
            <form method="POST" action="{{ route('anonymizations.store', $document) }}"
                  onsubmit="return confirm('Wygenerować wersję od nowa? Ręczne poprawki i zatwierdzenie zostaną utracone.')">
                @csrf
                <button class="text-sm text-gray-500 dark:text-gray-400 underline hover:no-underline">
                    Wygeneruj wersję od nowa
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
