@php
    $input = 'mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Zapisy online</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 rounded-md p-3 text-sm">{{ session('status') }}</div>
            @endif

            {{-- Waiting for approval --}}
            @if ($pending->isNotEmpty())
                <section class="bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 rounded-lg p-5 text-gray-900 dark:text-gray-100">
                    <h3 class="font-semibold">Do potwierdzenia ({{ $pending->count() }})</h3>
                    <ul class="mt-3 divide-y divide-amber-200 dark:divide-amber-800">
                        @foreach ($pending as $appointment)
                            <li class="py-2 flex flex-wrap items-center justify-between gap-3 text-sm">
                                <a href="{{ route('appointments.show', $appointment) }}" class="hover:underline">
                                    <strong>{{ $appointment->starts_at->format('d.m.Y H:i') }}</strong> — {{ $appointment->patient?->last_name }} {{ $appointment->patient?->first_name }}, tel. {{ $appointment->patient?->phone }}
                                @if ($problem = $appointment->reportedProblem())
                                    <span class="block text-xs text-gray-600 dark:text-gray-400 mt-0.5">„{{ \Illuminate\Support\Str::limit($problem, 140) }}”</span>
                                @endif
                                @if ($appointment->bookedFromOtherPhone())
                                    <span class="ml-1 text-xs px-1.5 py-0.5 rounded bg-amber-200 text-amber-900">zapis z innego numeru: {{ \App\Services\Messaging\PhoneNumber::format($appointment->booking_phone) }}</span>
                                @endif
                                </a>
                                @include('booking._decision', ['appointment' => $appointment])
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Prerequisites --}}
            <section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-sm text-gray-900 dark:text-gray-100 space-y-2">
                <h3 class="font-semibold text-base">Co jest potrzebne</h3>
                <p>{{ $smsReady ? '✅' : ($requiresSmsCode ? '❌' : '⚠️') }} Bramka SMS skonfigurowana — {{ $requiresSmsCode ? 'pacjent potwierdza numer kodem SMS' : 'kod SMS jest wyłączony, ale bez bramki pacjent nie dostanie SMS-a z potwierdzeniem' }}.
                    @unless ($smsReady) <span class="text-gray-500">Ustawia ją administrator w „Ustawieniach wysyłki”.</span> @endunless</p>
                <p>{{ $hasSchedule ? '✅' : '⚠️' }} Własny <a href="{{ route('schedule.edit') }}" class="underline">czas pracy</a>
                    @unless ($hasSchedule) <span class="text-gray-500">— bez niego pacjenci zobaczą domyślne godziny gabinetu.</span> @endunless</p>
                <p>{{ filled($user->practice_phone) ? '✅' : '⚠️' }} Telefon gabinetu w <a href="{{ route('profile.edit') }}" class="underline">profilu</a> — pokazywany pacjentom do kontaktu.</p>

                @if ($testMode)
                    <p>🧪 Tryb testowy zapisów jest włączony — zamiast SMS-a działa kod <strong class="font-mono">{{ \App\Services\Booking\OnlineBooking::TEST_CODE }}</strong>.</p>
                @endif

                @if ($user->online_booking_enabled && ($smsReady || $testMode || ! $requiresSmsCode))
                    <div class="mt-3 p-3 rounded-md bg-indigo-50 dark:bg-indigo-900/30">
                        Twoja strona zapisów: <a href="{{ route('booking.show', $user) }}" target="_blank" class="font-mono underline break-all">{{ route('booking.show', $user) }}</a>
                        <br><span class="text-xs text-gray-600 dark:text-gray-400">Wspólna strona wszystkich fizjoterapeutów: {{ route('booking.index') }}</span>
                    </div>
                @endif
            </section>

            {{-- Settings --}}
            <form method="POST" action="{{ route('booking.settings.update') }}" class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 space-y-4 text-gray-900 dark:text-gray-100">
                @csrf
                @method('PUT')

                <label class="flex items-center gap-2 font-medium">
                    <input type="hidden" name="online_booking_enabled" value="0">
                    <input type="checkbox" name="online_booking_enabled" value="1" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600" @checked(old('online_booking_enabled', $user->online_booking_enabled))>
                    Przyjmuję zapisy przez internet
                </label>

                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Stali pacjenci (ten sam telefon i nazwisko co w karcie) mają wizytę potwierdzoną od razu. Nowi pacjenci rezerwują termin, a Ty go potwierdzasz albo odrzucasz — pacjent dostaje SMS.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="booking_visit_minutes" value="Czas wizyty (min)" />
                        <input id="booking_visit_minutes" name="booking_visit_minutes" type="number" step="{{ $slotMinutes }}" min="{{ $slotMinutes }}" value="{{ old('booking_visit_minutes', $user->booking_visit_minutes ?? $slotMinutes) }}" class="{{ $input }}">
                        <x-input-error :messages="$errors->get('booking_visit_minutes')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="booking_first_visit_minutes" value="Czas pierwszej wizyty (min)" />
                        <input id="booking_first_visit_minutes" name="booking_first_visit_minutes" type="number" step="{{ $slotMinutes }}" min="{{ $slotMinutes }}" value="{{ old('booking_first_visit_minutes', $user->booking_first_visit_minutes) }}" placeholder="jak zwykła" class="{{ $input }}">
                        <x-input-error :messages="$errors->get('booking_first_visit_minutes')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="booking_min_notice_hours" value="Najwcześniej na ile godzin przed wizytą" />
                        <input id="booking_min_notice_hours" name="booking_min_notice_hours" type="number" min="0" max="168" value="{{ old('booking_min_notice_hours', $user->booking_min_notice_hours) }}" class="{{ $input }}">
                    </div>
                    <div>
                        <x-input-label for="booking_days_ahead" value="Na ile dni do przodu" />
                        <input id="booking_days_ahead" name="booking_days_ahead" type="number" min="1" max="120" value="{{ old('booking_days_ahead', $user->booking_days_ahead) }}" class="{{ $input }}">
                    </div>
                    <div>
                        <x-input-label for="booking_cancel_hours" value="Odwołanie przez link najpóźniej (godz. przed)" />
                        <input id="booking_cancel_hours" name="booking_cancel_hours" type="number" min="0" max="168" value="{{ old('booking_cancel_hours', $user->booking_cancel_hours) }}" class="{{ $input }}">
                    </div>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Czas wizyty musi być wielokrotnością Twojego slotu ({{ $slotMinutes }} min).</p>

                <x-primary-button>Zapisz</x-primary-button>
            </form>
        </div>
    </div>
</x-app-layout>
