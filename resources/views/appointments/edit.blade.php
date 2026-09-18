<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Wizyta: {{ $appointment->patient->last_name }} {{ $appointment->patient->first_name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('appointments.update', $appointment) }}"
                      x-data="slotPicker({
                          date: @js($appointment->starts_at->format('Y-m-d')),
                          slots: @js($slots->map(fn ($s) => [
                              'value' => $s['starts_at']->format('Y-m-d H:i:s'),
                              'label' => $s['starts_at']->format('H:i'),
                              'available' => $s['available'],
                          ])->values()),
                          workingDay: true,
                          slotMinutes: @js($slotMinutes),
                          maxDuration: @js($maxDuration),
                          selected: @js(old('starts_at', $appointment->starts_at->format('Y-m-d H:i:s'))),
                          duration: @js((int) old('duration_minutes', $appointment->starts_at->diffInMinutes($appointment->ends_at))),
                          slotsUrl: @js(route('appointments.slots')),
                      })">
                    @csrf
                    @method('PUT')

                    <div class="space-y-6">
                        <div>
                            <x-input-label for="date" value="Dzień" />
                            <x-text-input id="date" type="date" class="block mt-1" x-model="date" @change="loadSlots()" />
                        </div>

                        <div>
                            <x-input-label value="Termin" />
                            <input type="hidden" name="starts_at" :value="selected">

                            <div class="mt-2 grid grid-cols-4 sm:grid-cols-6 gap-2">
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
                            <x-input-error :messages="$errors->get('starts_at')" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="duration_minutes" value="Czas trwania" />
                                <select id="duration_minutes" name="duration_minutes" x-model="duration"
                                        class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                                    <template x-for="option in durationOptions()" :key="option">
                                        <option :value="option" x-text="formatDuration(option)"></option>
                                    </template>
                                </select>
                                <x-input-error :messages="$errors->get('duration_minutes')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="status" value="Status" />
                                <select id="status" name="status" required
                                        class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                                    @foreach (\App\Enums\AppointmentStatus::cases() as $status)
                                        <option value="{{ $status->value }}" @selected(old('status', $appointment->status->value) === $status->value)>
                                            {{ $status->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('status')" class="mt-2" />
                            </div>
                        </div>

                        @include('appointments._therapy-cycle-picker', [
                            'appointment' => $appointment,
                            'therapyCycles' => $therapyCycles,
                        ])

                        @include('appointments._icd10-picker', ['appointment' => $appointment])

                        <x-form-textarea name="interview" label="Wywiad" :value="$appointment->interview" :rows="5"
                                         hint="Ograniczenia w codziennych aktywnościach, czas trwania problemu, co nasila i co łagodzi objawy, cele pacjenta." />

                        @include('appointments._pain-map', ['appointment' => $appointment])

                        @include('appointments._comorbidities', ['appointment' => $appointment])

                        <x-form-textarea name="examination" label="Badanie stanu funkcjonowania" :value="$appointment->examination" :rows="4"
                                         hint="Zgłoszone przez pacjenta i zaobserwowane zmiany oraz ograniczenia w funkcjonowaniu." />

                        <x-form-textarea name="detailed_examination" label="Badania szczegółowe" :value="$appointment->detailed_examination" :rows="4"
                                         hint="Opis zaburzonych struktur i funkcji powiązanych ze zgłaszanym problemem." />

                        @include('appointments._measurements', [
                            'appointment' => $appointment,
                            'measurementTemplates' => $measurementTemplates,
                        ])

                        <x-form-textarea name="conclusions" label="Wnioski z badania" :value="$appointment->conclusions" :rows="4"
                                         hint="Hipotezy kliniczne wyjaśniające przyczynę ograniczenia funkcjonalnego." />

                        @include('appointments._therapy-plan', ['appointment' => $appointment])

                        <x-form-textarea name="procedures" label="Wykonane zabiegi" :value="$appointment->procedures" :rows="3" />

                        <x-form-textarea name="treatment_notes" label="Przebieg wizyty" :value="$appointment->treatment_notes" :rows="5" />

                        <x-form-textarea name="internal_notes" label="Notatka wewnętrzna" :value="$appointment->internal_notes" :rows="3"
                                         hint="Widoczna tylko dla Ciebie — nie trafia do wydruku przekazywanego pacjentowi." />

                        <x-form-textarea name="patient_recommendations" label="Zalecenia dla pacjenta" :value="$appointment->patient_recommendations" :rows="4"
                                         hint="Treść przeznaczona dla pacjenta: ćwiczenia domowe, profilaktyka, dalsze kroki." />
                    </div>

                    <div class="flex items-center gap-4 mt-6">
                        <x-primary-button>Zapisz</x-primary-button>
                        <a href="{{ route('appointments.show', $appointment) }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Anuluj</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
