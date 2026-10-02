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

            @if ($pendingBookings->isNotEmpty())
                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="font-semibold mb-3">Nowi pacjenci z zapisów online — do potwierdzenia ({{ $pendingBookings->count() }})</h3>
                    @foreach ($pendingBookings as $appointment)
                        <div class="flex flex-wrap items-center justify-between gap-3 py-2 text-sm border-b border-amber-200 dark:border-amber-800 last:border-0">
                            <a href="{{ route('appointments.show', $appointment) }}" class="hover:underline">
                                <strong>{{ $appointment->starts_at->format('d.m H:i') }}</strong> — {{ $appointment->patient->last_name }} {{ $appointment->patient->first_name }}, tel. {{ $appointment->patient->phone }}
                            </a>
                            @include('booking._decision', ['appointment' => $appointment])
                        </div>
                    @endforeach
                </div>
            @endif

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
