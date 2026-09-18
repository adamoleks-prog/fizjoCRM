<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Pulpit</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Wizyty dzisiaj</div>
                    <div class="text-3xl font-semibold">{{ $todayAppointments->count() }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Zaplanowane wizyty</div>
                    <div class="text-3xl font-semibold">{{ $upcomingCount }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Pacjenci</div>
                    <div class="text-3xl font-semibold">{{ $patientCount }}</div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                <h3 class="font-semibold mb-4">Dzisiejszy harmonogram</h3>

                @forelse ($todayAppointments as $appointment)
                    <a href="{{ route('appointments.show', $appointment) }}"
                       class="flex items-center justify-between border-b dark:border-gray-700 py-3 text-sm last:border-0 hover:bg-gray-50 dark:hover:bg-gray-700 -mx-2 px-2 rounded">
                        <span class="font-medium">{{ $appointment->starts_at->format('H:i') }}–{{ $appointment->ends_at->format('H:i') }}</span>
                        <span class="flex-1 px-4">{{ $appointment->patient->last_name }} {{ $appointment->patient->first_name }}</span>
                        <span class="text-gray-500 dark:text-gray-400">{{ $appointment->status->label() }}</span>
                    </a>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Brak wizyt zaplanowanych na dziś.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
