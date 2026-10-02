@php
    $input = 'mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Rodzaje wizyt</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 rounded-md p-3 text-sm">{{ session('status') }}</div>
            @endif

            <p class="text-sm text-gray-600 dark:text-gray-400">
                Rodzaj wybiera się przy umawianiu wizyty w panelu i w zapisach online. Dla fizjoterapii w zapisach online liczą się czasy ustawione przez fizjoterapeutę w „Zapisach online” (zwykła i pierwsza wizyta); dla pozostałych — czas podany tutaj, zaokrąglony w górę do slotu fizjoterapeuty.
            </p>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg divide-y divide-gray-100 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                @foreach ($services as $service)
                    <div class="px-6 py-3 flex flex-wrap items-center justify-between gap-3 text-sm">
                        <div @class(['opacity-50' => ! $service->active])>
                            <span class="font-medium">{{ $service->name }}</span>
                            <span class="text-gray-500 dark:text-gray-400">· {{ $service->duration_minutes }} min</span>
                            @if ($service->is_default) <span class="ml-1 text-xs px-1.5 py-0.5 rounded bg-indigo-100 dark:bg-indigo-900/40 text-indigo-800 dark:text-indigo-300">domyślny</span> @endif
                            @if ($service->online_bookable && $service->active) <span class="ml-1 text-xs text-emerald-700 dark:text-emerald-400">w zapisach online</span> @endif
                            @unless ($service->active) <span class="ml-1 text-xs text-gray-500">wyłączony</span> @endunless
                        </div>
                        <a href="{{ route('admin.services.index', ['edit' => $service->id]) }}#form" class="text-indigo-600 dark:text-indigo-400 hover:underline">Edytuj</a>
                    </div>
                @endforeach
            </div>

            <form id="form" method="POST" action="{{ $editing ? route('admin.services.update', $editing) : route('admin.services.store') }}"
                  class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 space-y-4 text-gray-900 dark:text-gray-100">
                @csrf
                @if ($editing) @method('PUT') @endif

                <h3 class="font-semibold">{{ $editing ? 'Edycja: '.$editing->name : 'Nowy rodzaj wizyty' }}</h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <x-input-label for="name" value="Nazwa" />
                        <input id="name" name="name" value="{{ old('name', $editing?->name) }}" required maxlength="80" placeholder="np. Masaż relaksacyjny" class="{{ $input }}">
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="duration_minutes" value="Czas (min)" />
                        <input id="duration_minutes" name="duration_minutes" type="number" min="5" max="240" step="5" value="{{ old('duration_minutes', $editing?->duration_minutes ?? 60) }}" required class="{{ $input }}">
                        <x-input-error :messages="$errors->get('duration_minutes')" class="mt-2" />
                    </div>
                </div>

                <input type="hidden" name="online_bookable" value="0">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="online_bookable" value="1" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600" @checked(old('online_bookable', $editing?->online_bookable ?? true))>
                    Dostępny w zapisach online
                </label>

                @unless ($editing?->is_default)
                    <input type="hidden" name="active" value="0">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="active" value="1" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600" @checked(old('active', $editing?->active ?? true))>
                        Aktywny (wyłączony nie pojawia się przy umawianiu, wcześniejsze wizyty zachowują nazwę)
                    </label>
                @endunless

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ $editing ? 'Zapisz' : 'Dodaj' }}</x-primary-button>
                    @if ($editing)
                        <a href="{{ route('admin.services.index') }}" class="text-sm text-gray-500 underline">Anuluj</a>
                    @endif
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
