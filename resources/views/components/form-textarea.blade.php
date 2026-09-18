@props([
    'name',
    'label',
    'value' => null,
    'rows' => 4,
    'hint' => null,
])

<div>
    <x-input-label :for="$name" :value="$label" />

    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}"
              {{ $attributes->merge([
                  'class' => 'block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm',
              ]) }}>{{ old($name, $value) }}</textarea>

    @if ($hint)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
    @endif

    <x-input-error :messages="$errors->get($name)" class="mt-2" />
</div>
