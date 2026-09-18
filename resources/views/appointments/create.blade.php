<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Nowa wizyta</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('appointments.store') }}"
                      x-data="slotPicker({
                          date: @js($date->format('Y-m-d')),
                          slots: @js($slots->map(fn ($s) => [
                              'value' => $s['starts_at']->format('Y-m-d H:i:s'),
                              'label' => $s['starts_at']->format('H:i'),
                              'available' => $s['available'],
                          ])->values()),
                          workingDay: @js($isWorkingDay),
                          slotMinutes: @js($slotMinutes),
                          maxDuration: @js($maxDuration),
                          selected: @js(old('starts_at')),
                          duration: @js((int) old('duration_minutes', $slotMinutes)),
                          slotsUrl: @js(route('appointments.slots')),
                      })">
                    @csrf

                    <div class="space-y-6">
                        <div>
                            <x-input-label for="patient_id" value="Pacjent" />
                            <select id="patient_id" name="patient_id" required
                                    class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                                <option value="">— wybierz —</option>
                                @foreach ($patients as $patient)
                                    <option value="{{ $patient->id }}" @selected(old('patient_id', $selectedPatientId) == $patient->id)>
                                        {{ $patient->last_name }} {{ $patient->first_name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('patient_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="date" value="Dzień" />
                            <x-text-input id="date" type="date" class="block mt-1" x-model="date" @change="loadSlots()" />
                        </div>

                        <div>
                            <x-input-label value="Wolne terminy" />
                            <input type="hidden" name="starts_at" :value="selected">

                            <template x-if="!workingDay">
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    Gabinet jest zamknięty w wybranym dniu.
                                </p>
                            </template>

                            <template x-if="workingDay && slots.length === 0">
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Ładowanie terminów…</p>
                            </template>

                            <div class="mt-2 grid grid-cols-4 sm:grid-cols-6 gap-2" x-show="workingDay">
                                <template x-for="slot in slots" :key="slot.value">
                                    <button type="button"
                                            @click="slot.available && select(slot)"
                                            :disabled="!slot.available"
                                            :class="{
                                                'bg-indigo-600 text-white border-indigo-600': selected === slot.value,
                                                'bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-700 hover:border-indigo-500': slot.available && selected !== slot.value,
                                                'bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 border-transparent cursor-not-allowed line-through': !slot.available,
                                            }"
                                            class="px-2 py-2 text-sm border rounded-md transition"
                                            x-text="slot.label"></button>
                                </template>
                            </div>

                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                Terminy zajęte są wyszarzone. Sloty co <span x-text="slotMinutes"></span> minut.
                            </p>
                            <x-input-error :messages="$errors->get('starts_at')" class="mt-2" />
                        </div>

                        <div x-show="selected">
                            <x-input-label for="duration_minutes" value="Czas trwania" />
                            <select id="duration_minutes" name="duration_minutes" x-model="duration"
                                    class="block mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                                <template x-for="option in durationOptions()" :key="option">
                                    <option :value="option" x-text="formatDuration(option)"></option>
                                </template>
                            </select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Dostępne tylko długości mieszczące się w kolejnych wolnych slotach.
                            </p>
                            <x-input-error :messages="$errors->get('duration_minutes')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex items-center gap-4 mt-6">
                        <x-primary-button ::disabled="!selected">Umów wizytę</x-primary-button>
                        <a href="{{ route('appointments.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Anuluj</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
