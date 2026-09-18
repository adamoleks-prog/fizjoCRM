@php
    $cycle = $appointment->therapyCycle;
    $horizons = \App\Enums\MilestoneHorizon::cases();

    $existing = old('milestones', $cycle
        ? $cycle->orderedMilestones()->map(fn ($m) => [
            'id' => $m->id,
            'goal' => $m->goal,
            'horizon' => $m->horizon->value,
            'achieved' => $m->isAchieved() ? '1' : '',
        ])->all()
        : []);
@endphp

<div class="rounded-md border border-gray-200 dark:border-gray-700 p-4">
    <h3 class="font-medium text-gray-900 dark:text-gray-100">Plan terapii</h3>

    @unless ($cycle)
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            Plan terapii dotyczy całego cyklu leczenia. Przypisz wizytę do cyklu powyżej i zapisz,
            aby móc zaplanować kolejne etapy.
        </p>
    @else
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Dotyczy całego cyklu „{{ $cycle->name }}" — zmiany będą widoczne przy każdej wizycie w tym cyklu.
        </p>

        <div class="mt-4">
            <x-form-textarea name="therapy_plan" label="Opis planowanych etapów terapii"
                             :value="$cycle->therapy_plan" :rows="4" />
        </div>

        <div class="mt-5" x-data="{
                rows: @js(array_values($existing)),
                add(horizon) { this.rows.push({ id: '', goal: '', horizon, achieved: '' }) },
                remove(i) { this.rows.splice(i, 1) },
             }">

            <x-input-label value="Kroki milowe" />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Cele krótko- i średnioterminowe prowadzące do celu długoterminowego. Odhacz krok, gdy pacjent go osiągnie.
            </p>

            <div class="mt-3 space-y-2">
                <template x-for="(row, i) in rows" :key="i">
                    <div class="flex flex-col sm:flex-row gap-2 items-start">
                        <input type="hidden" x-bind:name="`milestones[${i}][id]`" x-bind:value="row.id">

                        <label class="flex items-center gap-2 pt-2 shrink-0">
                            <input type="checkbox" value="1" x-model="row.achieved"
                                   x-bind:name="`milestones[${i}][achieved]`"
                                   class="rounded border-gray-300 dark:border-gray-700 text-indigo-600">
                            <span class="text-xs text-gray-500 dark:text-gray-400">osiągnięty</span>
                        </label>

                        <input type="text" x-model="row.goal" x-bind:name="`milestones[${i}][goal]`"
                               placeholder="np. samodzielne chodzenie bez kul"
                               class="flex-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm"
                               x-bind:class="row.achieved ? 'line-through text-gray-400' : ''">

                        <select x-model="row.horizon" x-bind:name="`milestones[${i}][horizon]`"
                                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                            @foreach ($horizons as $horizon)
                                <option value="{{ $horizon->value }}">{{ $horizon->label() }}</option>
                            @endforeach
                        </select>

                        <button type="button" @click="remove(i)"
                                class="px-2 py-2 text-sm text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400">
                            Usuń
                        </button>
                    </div>
                </template>
            </div>

            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400" x-show="rows.length === 0">
                Brak kroków milowych.
            </p>

            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($horizons as $horizon)
                    <button type="button" @click="add(@js($horizon->value))"
                            class="px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-700 rounded-md hover:border-indigo-500 text-gray-700 dark:text-gray-300">
                        + {{ $horizon->label() }}
                    </button>
                @endforeach
            </div>

            <x-input-error :messages="$errors->get('milestones')" class="mt-2" />
        </div>
    @endunless
</div>
