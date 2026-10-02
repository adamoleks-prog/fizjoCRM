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
                                @if ($appointment->bookedFromOtherPhone())
                                    <span class="ml-1 text-xs px-1.5 py-0.5 rounded bg-amber-200 text-amber-900">zapis z innego numeru: {{ \App\Services\Messaging\PhoneNumber::format($appointment->booking_phone) }}</span>
                                @endif
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

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                <div class="flex items-baseline justify-between mb-4">
                    <h3 class="font-semibold">Kolejne wizyty <span class="font-normal text-sm text-gray-500 dark:text-gray-400">· najbliższe 7 dni</span></h3>
                    <a href="{{ route('appointments.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Kalendarz</a>
                </div>

                @forelse ($upcoming as $day => $appointments)
                    @php($date = \Carbon\Carbon::parse($day))
                    <div class="mt-4 first:mt-0">
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            {{ $date->isTomorrow() ? 'Jutro' : ucfirst($date->translatedFormat('l')) }}, {{ $date->format('d.m') }}
                            <span class="font-normal normal-case">· {{ $appointments->count() }} {{ $appointments->count() === 1 ? 'wizyta' : ($appointments->count() < 5 ? 'wizyty' : 'wizyt') }}</span>
                        </div>
                        @foreach ($appointments as $appointment)
                            <a href="{{ route('appointments.show', $appointment) }}"
                               class="flex items-center justify-between border-b dark:border-gray-700 py-2.5 text-sm last:border-0 hover:bg-gray-50 dark:hover:bg-gray-700 -mx-2 px-2 rounded">
                                <span class="font-medium w-24 shrink-0">{{ $appointment->starts_at->format('H:i') }}–{{ $appointment->ends_at->format('H:i') }}</span>
                                <span class="flex-1 px-4">{{ $appointment->patient->last_name }} {{ $appointment->patient->first_name }}</span>
                                <span class="text-gray-500 dark:text-gray-400 text-right">
                                    {{ $appointment->serviceName() }}@if ($appointment->status === \App\Enums\AppointmentStatus::Pending) · <span class="text-amber-600 dark:text-amber-400">do potwierdzenia</span>@endif
                                </span>
                            </a>
                        @endforeach
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Brak wizyt w najbliższych 7 dniach.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
