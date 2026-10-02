@php($patient = $patient ?? null)

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-input-label for="first_name" value="Imię" />
        <x-text-input id="first_name" name="first_name" class="block mt-1 w-full" required maxlength="40"
                      oninput="this.value = this.value.replace(/[^\p{L}]/gu, '')"
                      value="{{ old('first_name', $patient?->first_name) }}" />
        <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="last_name" value="Nazwisko" />
        <x-text-input id="last_name" name="last_name" class="block mt-1 w-full" required maxlength="60"
                      oninput="this.value = this.value.replace(/[^\p{L}-]/gu, '').replace(/-{2,}/g, '-')"
                      value="{{ old('last_name', $patient?->last_name) }}" />
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Same litery; nazwisko dwuczłonowe połącz myślnikiem.</p>
        <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="phone" value="Telefon" />
        <div class="mt-1 flex rounded-md shadow-sm">
            <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 select-none">+48</span>
            <input id="phone" name="phone" type="tel" inputmode="numeric" maxlength="9" placeholder="600123456"
                   value="{{ old('phone', \App\Support\PersonalData::nationalDigits($patient?->phone)) }}"
                   oninput="this.value = this.value.replace(/\D/g, '').slice(0, 9)"
                   class="block w-full rounded-none rounded-r-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500">
        </div>
        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="email" value="E-mail" />
        <x-text-input id="email" name="email" type="email" class="block mt-1 w-full" maxlength="255"
                      pattern="[^@\s]+@[^@\s]+\.[A-Za-z]{2,}" title="Adres e-mail, np. jan.kowalski@gmail.com"
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

    <div class="md:col-span-2">
        <input type="hidden" name="reminders_enabled" value="0">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" name="reminders_enabled" value="1"
                   class="rounded border-gray-300 dark:border-gray-700 text-indigo-600"
                   @checked(old('reminders_enabled', $patient?->reminders_enabled ?? true))>
            Wysyłaj przypomnienia o wizytach (SMS / e-mail)
        </label>
    </div>

    @isset($operators)
        @if ($operators->isNotEmpty())
            <div>
                <x-input-label for="operator_id" value="Fizjoterapeuta prowadzący" />
                <select id="operator_id" name="operator_id" required
                        class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm">
                    <option value="">— wybierz —</option>
                    @foreach ($operators as $operator)
                        <option value="{{ $operator->id }}" @selected(old('operator_id') == $operator->id)>{{ $operator->name }}{{ $operator->isAdmin() ? ' (administrator)' : '' }}</option>
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
