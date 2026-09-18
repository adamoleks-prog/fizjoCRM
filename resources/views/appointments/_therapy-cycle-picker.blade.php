@php
    $creatingNew = old('new_therapy_cycle_name') !== null;
    $currentCycleId = (string) old('therapy_cycle_id', $appointment->therapy_cycle_id);
@endphp

{{-- Only the input matching the chosen mode stays enabled: a disabled field is not
     submitted, which is how the controller tells "pick existing" from "start new". --}}
<div x-data="{
        mode: @js($creatingNew ? 'new' : 'existing'),
        cycleId: @js($creatingNew ? '' : $currentCycleId),
     }">

    <x-input-label for="therapy_cycle_select" value="Cykl terapeutyczny" />

    <select id="therapy_cycle_select"
            @change="mode = $event.target.value === '__new__' ? 'new' : 'existing';
                     cycleId = mode === 'new' ? '' : $event.target.value"
            class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
        <option value="" @selected(! $creatingNew && $currentCycleId === '')>— bez cyklu —</option>
        @foreach ($therapyCycles as $cycle)
            <option value="{{ $cycle->id }}" @selected(! $creatingNew && $currentCycleId === (string) $cycle->id)>
                {{ $cycle->name }}@if ($diagnosis = $cycle->primaryIcd10Name()) — {{ $diagnosis }}@endif
            </option>
        @endforeach
        <option value="__new__" @selected($creatingNew)>+ Nowy cykl</option>
    </select>

    <input type="hidden" name="therapy_cycle_id" x-bind:value="cycleId" x-bind:disabled="mode !== 'existing'">

    <div class="mt-2" x-show="mode === 'new'" x-cloak>
        <x-text-input type="text" name="new_therapy_cycle_name" class="block w-full"
                      value="{{ old('new_therapy_cycle_name') }}"
                      placeholder="Nazwa cyklu (opcjonalnie, np. Rehabilitacja kolana)"
                      x-bind:disabled="mode !== 'new'" />
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Puste pole — nazwa zostanie nadana automatycznie.
        </p>
    </div>

    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="mode !== 'new'">
        Grupuje wizyty dotyczące tego samego schorzenia.
    </p>

    <x-input-error :messages="$errors->get('therapy_cycle_id')" class="mt-2" />
    <x-input-error :messages="$errors->get('new_therapy_cycle_name')" class="mt-2" />
</div>
