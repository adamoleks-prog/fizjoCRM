@php($patient = $patient ?? null)

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-input-label for="first_name" value="Imię" />
        <x-text-input id="first_name" name="first_name" class="block mt-1 w-full" required
                      value="{{ old('first_name', $patient?->first_name) }}" />
        <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="last_name" value="Nazwisko" />
        <x-text-input id="last_name" name="last_name" class="block mt-1 w-full" required
                      value="{{ old('last_name', $patient?->last_name) }}" />
        <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="phone" value="Telefon" />
        <x-text-input id="phone" name="phone" class="block mt-1 w-full"
                      value="{{ old('phone', $patient?->phone) }}" />
        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="email" value="E-mail" />
        <x-text-input id="email" name="email" type="email" class="block mt-1 w-full"
                      value="{{ old('email', $patient?->email) }}" />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="date_of_birth" value="Data urodzenia" />
        <x-text-input id="date_of_birth" name="date_of_birth" type="date" class="block mt-1 w-full"
                      value="{{ old('date_of_birth', $patient?->date_of_birth?->format('Y-m-d')) }}" />
        <x-input-error :messages="$errors->get('date_of_birth')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="address" value="Adres" />
        <x-text-input id="address" name="address" class="block mt-1 w-full"
                      value="{{ old('address', $patient?->address) }}" />
        <x-input-error :messages="$errors->get('address')" class="mt-2" />
    </div>

    @isset($operators)
        @if ($operators->isNotEmpty())
            <div>
                <x-input-label for="operator_id" value="Operator prowadzący" />
                <select id="operator_id" name="operator_id" required
                        class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm">
                    <option value="">— wybierz —</option>
                    @foreach ($operators as $operator)
                        <option value="{{ $operator->id }}" @selected(old('operator_id') == $operator->id)>{{ $operator->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('operator_id')" class="mt-2" />
            </div>
        @endif
    @endisset
</div>

<div class="mt-4">
    <x-input-label for="notes" value="Uwagi" />
    <textarea id="notes" name="notes" rows="4"
              class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm">{{ old('notes', $patient?->notes) }}</textarea>
    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
</div>
