@php
    $input = 'mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Wzory zgód</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 rounded-md p-3 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-md p-4 text-sm text-amber-900 dark:text-amber-200">
                Wzory startowe to szkic — przed pierwszym podpisem daj je do sprawdzenia prawnikowi lub inspektorowi ochrony danych i uzupełnij dane administratora (np. NIP, adres, kontakt).
                Zgodę podpisuje się na karcie pacjenta przyciskiem „Podpisz zgodę na tablecie”.
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg divide-y divide-gray-100 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                @foreach ($templates as $template)
                    <div class="px-6 py-3 flex flex-wrap items-center justify-between gap-3">
                        <span class="font-medium">{{ $template->name }}</span>
                        <div class="flex items-center gap-4 text-sm">
                            <a href="{{ route('consent-templates.index', ['edit' => $template->id]) }}#form" class="text-indigo-600 dark:text-indigo-400 hover:underline">Edytuj</a>
                            <form method="POST" action="{{ route('consent-templates.destroy', $template) }}" onsubmit="return confirm('Usunąć ten wzór? Podpisane zgody zostaną.')">
                                @csrf
                                @method('DELETE')
                                <button class="text-red-600 dark:text-red-400 hover:underline">Usuń</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <form id="form" method="POST"
                  action="{{ $editing ? route('consent-templates.update', $editing) : route('consent-templates.store') }}"
                  class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 space-y-4 text-gray-900 dark:text-gray-100">
                @csrf
                @if ($editing)
                    @method('PUT')
                @endif

                <h3 class="font-semibold">{{ $editing ? 'Edycja: '.$editing->name : 'Nowy wzór zgody' }}</h3>

                <div>
                    <x-input-label for="name" value="Nazwa" />
                    <input id="name" name="name" value="{{ old('name', $editing?->name) }}" class="{{ $input }}" required>
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="body" value="Treść" />
                    <textarea id="body" name="body" rows="14" class="{{ $input }}" required>{{ old('body', $editing?->body) }}</textarea>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Pola wstawiane automatycznie: {{ implode(', ', \App\Models\ConsentTemplate::PLACEHOLDERS) }}.
                        Dane gabinetu uzupełnisz w Profilu.
                    </p>
                    <x-input-error :messages="$errors->get('body')" class="mt-2" />
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ $editing ? 'Zapisz' : 'Dodaj' }}</x-primary-button>
                    @if ($editing)
                        <a href="{{ route('consent-templates.index') }}" class="text-sm text-gray-500 underline">Anuluj edycję</a>
                    @endif
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
