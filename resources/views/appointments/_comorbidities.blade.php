@php
    $kinds = \App\Enums\ComorbidityKind::cases();

    $existing = old('comorbidities', $appointment->patient->orderedComorbidities()->map(fn ($c) => [
        'id' => $c->id,
        'name' => $c->name,
        'kind' => $c->kind->value,
    ])->all());
@endphp

{{-- Stored on the patient, so edits here carry over to every visit. --}}
<div class="rounded-md border border-gray-200 dark:border-gray-700 p-4"
     x-data="{
        rows: @js(array_values($existing)),
        add() { this.rows.push({ id: '', name: '', kind: 'active' }) },
        remove(i) { this.rows.splice(i, 1) },
     }">

    <h3 class="font-medium text-gray-900 dark:text-gray-100">Choroby współistniejące</h3>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        Zapisywane w karcie pacjenta — będą widoczne przy każdej jego wizycie.
    </p>

    <div class="mt-3 space-y-2">
        <template x-for="(row, i) in rows" :key="i">
            <div class="flex flex-col sm:flex-row gap-2 items-start">
                <input type="hidden" x-bind:name="`comorbidities[${i}][id]`" x-bind:value="row.id">

                <input type="text" x-model="row.name" x-bind:name="`comorbidities[${i}][name]`"
                       placeholder="np. cukrzyca typu 2, nadciśnienie tętnicze"
                       class="flex-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">

                <select x-model="row.kind" x-bind:name="`comorbidities[${i}][kind]`"
                        class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                    @foreach ($kinds as $kind)
                        <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                    @endforeach
                </select>

                <button type="button" @click="remove(i)"
                        class="px-2 py-2 text-sm text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400">
                    Usuń
                </button>
            </div>
        </template>
    </div>

    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400" x-show="rows.length === 0">
        Brak chorób współistniejących.
    </p>

    <button type="button" @click="add()"
            class="mt-3 px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-700 rounded-md hover:border-indigo-500 text-gray-700 dark:text-gray-300">
        + Dodaj schorzenie
    </button>

    <x-input-error :messages="$errors->get('comorbidities')" class="mt-2" />
</div>
