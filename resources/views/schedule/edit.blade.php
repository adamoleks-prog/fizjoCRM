@php
    $input = 'border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm';
    $keep = $operator->id !== auth()->id() ? ['operator' => $operator->id] : [];
    $toRows = fn ($periods) => collect($periods)->map(fn ($p) => ['start' => is_string($p[0]) ? $p[0] : $p[0]->format('H:i'), 'end' => is_string($p[1]) ? $p[1] : $p[1]->format('H:i')])->values();
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Czas pracy{{ $operator->id !== auth()->id() ? ': '.$operator->name : '' }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

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

            @if ($operators->isNotEmpty())
                <form method="GET" class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <label for="operator">Grafik osoby:</label>
                    <select id="operator" name="operator" onchange="this.form.submit()" class="{{ $input }}">
                        @foreach ($operators as $option)
                            <option value="{{ $option->id }}" @selected($option->id === $operator->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            {{-- Weekly pattern --}}
            <form method="POST" action="{{ route('schedule.pattern', $keep) }}"
                  class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100 space-y-4">
                @csrf
                @method('PUT')

                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h3 class="font-semibold">Standardowy tydzień</h3>
                    @unless ($hasOwnPattern)
                        <span class="text-xs text-amber-700 dark:text-amber-400">Nie masz jeszcze własnego grafiku — poniżej domyślne godziny gabinetu.</span>
                    @endunless
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400">Te godziny powtarzają się co tydzień. Przerwę w ciągu dnia ustawisz, dodając drugi przedział (np. 8:00–12:00 i 14:00–18:00).</p>

                <div class="max-w-xs">
                    <x-input-label for="slot_minutes" value="Długość slotu (najkrótsza wizyta)" />
                    <select id="slot_minutes" name="slot_minutes" class="mt-1 block w-full {{ $input }}">
                        @foreach (\App\Services\WorkSchedule::SLOT_CHOICES as $choice)
                            <option value="{{ $choice }}" @selected($slotMinutes === $choice)>{{ $choice }} min</option>
                        @endforeach
                    </select>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach (\App\Services\WorkSchedule::WEEKDAYS as $weekday => $label)
                        @php $rows = $toRows($pattern[$weekday] ?? []); @endphp
                        <div class="py-3 flex flex-wrap items-start gap-4"
                             x-data="{ works: @js($rows->isNotEmpty()), rows: @js($rows->isNotEmpty() ? $rows : [['start' => '08:00', 'end' => '16:00']]) }">
                            <label class="w-36 flex items-center gap-2 pt-1.5 text-sm font-medium">
                                <input type="hidden" name="works[{{ $weekday }}]" value="0">
                                <input type="checkbox" name="works[{{ $weekday }}]" value="1" x-model="works" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600">
                                {{ $label }}
                            </label>

                            <div class="flex-1 space-y-2" x-show="works">
                                <template x-for="(row, i) in rows" :key="i">
                                    <div class="flex items-center gap-2">
                                        <input type="time" step="300" :name="`pattern[{{ $weekday }}][${i}][start]`" x-model="row.start" :disabled="!works" class="{{ $input }}">
                                        <span>–</span>
                                        <input type="time" step="300" :name="`pattern[{{ $weekday }}][${i}][end]`" x-model="row.end" :disabled="!works" class="{{ $input }}">
                                        <button type="button" x-show="rows.length > 1" @click="rows.splice(i, 1)" class="text-xs text-red-600 dark:text-red-400 hover:underline">usuń</button>
                                    </div>
                                </template>
                                <button type="button" x-show="rows.length < 4" @click="rows.push({ start: '14:00', end: '18:00' })"
                                        class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">+ przedział (np. po przerwie)</button>
                            </div>
                            <span class="pt-1.5 text-sm text-gray-400" x-show="!works">wolne</span>
                        </div>
                    @endforeach
                </div>

                <x-primary-button>Zapisz standardowy tydzień</x-primary-button>
            </form>

            {{-- Single day editor --}}
            @if ($editDate)
                @php $editRows = $toRows($editPeriods); @endphp
                <form id="day" method="POST" action="{{ route('schedule.day', $keep) }}"
                      class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100 space-y-4 ring-2 ring-indigo-400"
                      x-data="{ off: @js($editRows->isEmpty()), rows: @js($editRows->isNotEmpty() ? $editRows : [['start' => '08:00', 'end' => '16:00']]) }">
                    @csrf
                    <input type="hidden" name="date" value="{{ $editDate->toDateString() }}">

                    <h3 class="font-semibold">{{ \App\Services\WorkSchedule::WEEKDAYS[$editDate->isoWeekday()] }}, {{ $editDate->format('d.m.Y') }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Zmiana dotyczy tylko tego dnia. Pozostałe tygodnie zostają bez zmian.</p>

                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="day_off" value="0">
                        <input type="checkbox" name="day_off" value="1" x-model="off" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600">
                        Dzień wolny
                    </label>

                    <div class="space-y-2" x-show="!off">
                        <template x-for="(row, i) in rows" :key="i">
                            <div class="flex items-center gap-2">
                                <input type="time" step="300" :name="`periods[${i}][start]`" x-model="row.start" :disabled="off" class="{{ $input }}">
                                <span>–</span>
                                <input type="time" step="300" :name="`periods[${i}][end]`" x-model="row.end" :disabled="off" class="{{ $input }}">
                                <button type="button" x-show="rows.length > 1" @click="rows.splice(i, 1)" class="text-xs text-red-600 dark:text-red-400 hover:underline">usuń</button>
                            </div>
                        </template>
                        <button type="button" x-show="rows.length < 4" @click="rows.push({ start: '14:00', end: '18:00' })" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">+ przedział</button>
                    </div>

                    <div class="max-w-md">
                        <x-input-label for="note" value="Notatka (opcjonalnie, np. urlop, szkolenie)" />
                        <input id="note" name="note" maxlength="120" class="mt-1 block w-full {{ $input }}">
                    </div>

                    @if ($editAppointments->isNotEmpty())
                        <div class="text-sm bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-900 dark:text-amber-200 rounded-md p-3">
                            Umówione wizyty tego dnia: {{ $editAppointments->map(fn ($a) => $a->starts_at->format('H:i').' '.$a->patient?->last_name)->implode(', ') }}.
                            Zmiana godzin ich nie odwołuje — jeśli wypadną poza nowymi godzinami, przełóż je ręcznie.
                        </div>
                    @endif

                    <div class="flex flex-wrap items-center gap-4">
                        <x-primary-button>Zapisz ten dzień</x-primary-button>
                        <a href="{{ route('schedule.edit', $keep) }}" class="text-sm text-gray-500 underline">Anuluj</a>
                    </div>
                </form>

                @if ($editIsException)
                    <form method="POST" action="{{ route('schedule.day.reset', $keep) }}">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="date" value="{{ $editDate->toDateString() }}">
                        <button class="text-sm text-indigo-600 dark:text-indigo-400 underline">Przywróć standardowe godziny dla tego dnia</button>
                    </form>
                @endif
            @endif

            {{-- Next weeks --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                <h3 class="font-semibold">Najbliższe tygodnie</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Kliknij dzień, żeby ustawić w nim inne godziny albo dzień wolny.</p>

                <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2">
                    @foreach ($days as $day)
                        @php $past = $day['date']->lt(today()); @endphp
                        <a href="{{ $past ? '#' : route('schedule.edit', [...$keep, 'date' => $day['date']->toDateString()]).'#day' }}"
                           @class([
                               'block rounded-md border p-2 text-xs',
                               'opacity-40 pointer-events-none' => $past,
                               'border-indigo-400 bg-indigo-50 dark:bg-indigo-900/30' => $day['exception'],
                               'border-gray-200 dark:border-gray-700 hover:border-indigo-400' => ! $day['exception'],
                               'ring-2 ring-indigo-500' => $editDate && $day['date']->isSameDay($editDate),
                           ])>
                            <div class="font-semibold">{{ mb_substr(\App\Services\WorkSchedule::WEEKDAYS[$day['date']->isoWeekday()], 0, 3) }} {{ $day['date']->format('d.m') }}</div>
                            @forelse ($day['periods'] as [$start, $end])
                                <div>{{ $start->format('H:i') }}–{{ $end->format('H:i') }}</div>
                            @empty
                                <div class="text-gray-400">wolne</div>
                            @endforelse
                            @if ($day['exception'])
                                <div class="mt-0.5 text-indigo-700 dark:text-indigo-300">zmienione</div>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
