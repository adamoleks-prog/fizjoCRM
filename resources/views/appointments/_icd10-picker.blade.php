@php
    $currentCode = old('icd10_code', $appointment->icd10_code);
    $currentName = $currentCode ? \App\Models\Icd10Code::where('code', $currentCode)->value('name') : null;
@endphp

<div x-data="icd10Picker({
        code: @js($currentCode),
        name: @js($currentName),
        searchUrl: @js(route('icd10.search')),
     })"
     @click.outside="open = false"
     class="relative">

    <x-input-label for="icd10_search" value="Rozpoznanie ICD-10" />

    <input type="hidden" name="icd10_code" :value="code">

    <div class="relative mt-1">
        <input id="icd10_search" type="text" autocomplete="off"
               x-model="query"
               @input="onInput()"
               @focus="onInput()"
               placeholder="Wpisz kod (np. M54) lub nazwę (np. rwa kulszowa)"
               class="block w-full pr-16 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm">

        <button type="button" x-show="code" @click="clear()"
                class="absolute inset-y-0 right-0 px-3 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
            Wyczyść
        </button>
    </div>

    <div x-show="open" x-cloak
         class="absolute z-20 mt-1 w-full max-h-72 overflow-y-auto bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg">

        <template x-if="loading">
            <div class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">Szukam…</div>
        </template>

        <template x-if="!loading && results.length === 0">
            <div class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">Brak wyników.</div>
        </template>

        <template x-for="item in results" :key="item.code">
            <button type="button" @click="choose(item)"
                    class="block w-full text-left px-3 py-2 hover:bg-indigo-50 dark:hover:bg-gray-800">
                <span class="font-mono text-sm font-semibold text-indigo-600 dark:text-indigo-400" x-text="item.code"></span>
                <span class="text-sm text-gray-900 dark:text-gray-100" x-text="item.name"></span>
                <span class="block text-xs text-gray-500 dark:text-gray-400" x-text="item.chapter"></span>
            </button>
        </template>
    </div>

    <x-input-error :messages="$errors->get('icd10_code')" class="mt-2" />
</div>
