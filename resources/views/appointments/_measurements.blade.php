@php
    $catalog = $measurementTemplates->map(fn ($t) => [
        'id' => $t->id,
        'name' => $t->name,
        'type' => $t->type->value,
        'unit' => $t->unit,
    ])->values();

    $existing = old('measurements', $appointment->measurements->map(fn ($m) => [
        'measurement_template_id' => (string) $m->measurement_template_id,
        'value_left' => $m->value_left,
        'value_right' => $m->value_right,
        'value_boolean' => $m->value_boolean ? '1' : '0',
        'note' => $m->note,
    ])->all());
@endphp

<div class="rounded-md border border-gray-200 dark:border-gray-700 p-4">
    <div class="flex items-center justify-between gap-3">
        <h3 class="font-medium text-gray-900 dark:text-gray-100">Pomiary</h3>
        <a href="{{ route('measurements.index') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
            Zarządzaj biblioteką
        </a>
    </div>

    @if ($measurementTemplates->isEmpty())
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            Nie masz jeszcze zdefiniowanych pomiarów.
            <a href="{{ route('measurements.index') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Dodaj pierwszy</a>,
            żeby móc zapisywać wyniki przy wizytach.
        </p>
    @else
        <div class="mt-3" x-data="{
                catalog: @js($catalog),
                rows: @js(array_values($existing)),
                add() { this.rows.push({ measurement_template_id: '', value_left: '', value_right: '', value_boolean: '1', note: '' }) },
                remove(i) { this.rows.splice(i, 1) },
                typeOf(row) { return this.catalog.find(c => String(c.id) === String(row.measurement_template_id))?.type ?? null },
                unitOf(row) { return this.catalog.find(c => String(c.id) === String(row.measurement_template_id))?.unit ?? '' },
             }">

            <div class="space-y-2">
                <template x-for="(row, i) in rows" :key="i">
                    <div class="flex flex-col sm:flex-row gap-2 items-start">
                        <select x-model="row.measurement_template_id"
                                x-bind:name="`measurements[${i}][measurement_template_id]`"
                                class="sm:w-64 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                            <option value="">— wybierz pomiar —</option>
                            <template x-for="item in catalog" :key="item.id">
                                <option :value="item.id" x-text="item.name"></option>
                            </template>
                        </select>

                        <div class="flex gap-2 items-center flex-1">
                            <template x-if="typeOf(row) === 'boolean'">
                                <select x-model="row.value_boolean" x-bind:name="`measurements[${i}][value_boolean]`"
                                        class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                                    <option value="1">Tak</option>
                                    <option value="0">Nie</option>
                                </select>
                            </template>

                            <template x-if="typeOf(row) === 'scale'">
                                <input type="number" min="0" max="10" step="1" x-model="row.value_left"
                                       x-bind:name="`measurements[${i}][value_left]`" placeholder="0-10"
                                       class="w-24 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                            </template>

                            <template x-if="typeOf(row) === 'numeric'">
                                <div class="flex items-center gap-1">
                                    <input type="number" step="0.01" x-model="row.value_left"
                                           x-bind:name="`measurements[${i}][value_left]`" placeholder="wynik"
                                           class="w-28 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                                    <span class="text-xs text-gray-500 dark:text-gray-400" x-text="unitOf(row)"></span>
                                </div>
                            </template>

                            <template x-if="typeOf(row) === 'bilateral'">
                                <div class="flex items-center gap-1">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">L</span>
                                    <input type="number" step="0.01" x-model="row.value_left"
                                           x-bind:name="`measurements[${i}][value_left]`"
                                           class="w-20 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">P</span>
                                    <input type="number" step="0.01" x-model="row.value_right"
                                           x-bind:name="`measurements[${i}][value_right]`"
                                           class="w-20 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                                    <span class="text-xs text-gray-500 dark:text-gray-400" x-text="unitOf(row)"></span>
                                </div>
                            </template>
                        </div>

                        <input type="text" x-model="row.note" x-bind:name="`measurements[${i}][note]`"
                               placeholder="uwaga (opcjonalnie)"
                               class="flex-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">

                        <button type="button" @click="remove(i)"
                                class="px-2 py-2 text-sm text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400">
                            Usuń
                        </button>
                    </div>
                </template>
            </div>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400" x-show="rows.length === 0">
                Brak pomiarów przy tej wizycie.
            </p>

            <button type="button" @click="add()"
                    class="mt-3 px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-700 rounded-md hover:border-indigo-500 text-gray-700 dark:text-gray-300">
                + Dodaj pomiar
            </button>
        </div>
    @endif
</div>
