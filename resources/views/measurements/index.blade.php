<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Pomiary</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 rounded-md p-3 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="font-medium text-gray-900 dark:text-gray-100">Twoja biblioteka pomiarów</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Pomiar definiujesz raz, a przy każdej wizycie wpisujesz tylko wynik — dzięki temu można porównywać postęp między wizytami.
                </p>

                @if ($templates->isEmpty())
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Nie masz jeszcze żadnych pomiarów.</p>
                @else
                    <table class="mt-4 w-full text-sm">
                        <thead class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="py-2">Nazwa</th>
                                <th class="py-2">Typ</th>
                                <th class="py-2">Jednostka</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($templates as $template)
                                <tr>
                                    <td class="py-2">
                                        {{ $template->name }}
                                        @if ($template->description)
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $template->description }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 text-gray-600 dark:text-gray-300">{{ $template->type->label() }}</td>
                                    <td class="py-2 text-gray-600 dark:text-gray-300">{{ $template->unit ?? '—' }}</td>
                                    <td class="py-2 text-right">
                                        <form method="POST" action="{{ route('measurements.destroy', $template) }}"
                                              onsubmit="return confirm('Usunąć pomiar z biblioteki? Wyniki zapisane w kartach wizyt zostaną zachowane.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400">Usuń</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="font-medium text-gray-900 dark:text-gray-100">Nowy pomiar</h3>

                <form method="POST" action="{{ route('measurements.store') }}" class="mt-4 space-y-4"
                      x-data="{ type: @js(old('type', 'numeric')) }">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Nazwa" />
                        <x-text-input id="name" name="name" class="block mt-1 w-full" :value="old('name')"
                                      placeholder="np. Zakres zgięcia stawu kolanowego" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" value="Opis (opcjonalnie)" />
                        <x-text-input id="description" name="description" class="block mt-1 w-full" :value="old('description')"
                                      placeholder="np. pomiar goniometrem w leżeniu tyłem" />
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="type" value="Typ pomiaru" />
                        <select id="type" name="type" x-model="type"
                                class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                            @foreach (\App\Enums\MeasurementType::cases() as $case)
                                <option value="{{ $case->value }}">{{ $case->label() }} — {{ $case->hint() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('type')" class="mt-2" />
                    </div>

                    <div x-show="['numeric', 'bilateral'].includes(type)" x-cloak>
                        <x-input-label for="unit" value="Jednostka" />
                        <x-text-input id="unit" name="unit" class="block mt-1" :value="old('unit')" placeholder="np. cm, °, kg" />
                        <x-input-error :messages="$errors->get('unit')" class="mt-2" />
                    </div>

                    <x-primary-button>Dodaj pomiar</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
