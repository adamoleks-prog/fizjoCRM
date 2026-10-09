@php
    $input = 'mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm';
    $badge = [
        'ok' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
        'warning' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'error' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    ];
    $badgeText = ['ok' => 'OK', 'warning' => 'Uwaga', 'error' => 'Problem'];
    $card = 'bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Stan systemu</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 rounded-md p-3 text-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 rounded-md p-3 text-sm">{{ $errors->first() }}</div>
            @endif

            {{-- Checks --}}
            <section class="{{ $card }}">
                <h3 class="font-semibold">Kontrola</h3>
                <div class="mt-3 divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($checks as $check)
                        <div class="py-2 flex flex-wrap sm:flex-nowrap items-start gap-3 text-sm">
                            <span class="shrink-0 w-16 text-center text-xs font-semibold px-2 py-0.5 rounded {{ $badge[$check['state']] }}">{{ $badgeText[$check['state']] }}</span>
                            <span class="sm:w-72 shrink-0 font-medium">{{ $check['label'] }}</span>
                            <span class="text-gray-600 dark:text-gray-400">{{ $check['detail'] }}</span>
                        </div>
                    @endforeach
                </div>
                @unless ($scheduler)
                    <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                        Wpis cron dla harmonogramu (dodaje się raz, w Virtualmin → Scheduled Cron Jobs albo <code>crontab -e</code>):<br>
                        <code class="select-all">* * * * * {{ PHP_BINARY }} {{ base_path('artisan') }} schedule:run &gt;/dev/null 2&gt;&amp;1</code>
                    </p>
                @endunless
            </section>

            {{-- Security summary --}}
            <section class="{{ $card }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-semibold">Bezpieczeństwo — ostatnie 24 h</h3>
                    <a href="{{ route('admin.security.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Dziennik bezpieczeństwa →</a>
                </div>
                @if ($security['total'] === 0)
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Brak zdarzeń.</p>
                @else
                    <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                        <ul class="space-y-1">
                            @foreach ($security['byType'] as $row)
                                <li class="flex justify-between gap-3">
                                    <a href="{{ route('admin.security.index', ['type' => $row->type->value]) }}" class="hover:underline">{{ $row->type->label() }}</a>
                                    <span class="font-semibold">{{ $row->total }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <div>
                            <p class="text-gray-600 dark:text-gray-400">Najaktywniejsze adresy IP (ostrzeżenia i zdarzenia krytyczne):</p>
                            <ul class="mt-1 space-y-1">
                                @forelse ($security['topIps'] as $row)
                                    <li class="flex justify-between gap-3">
                                        <a href="{{ route('admin.security.index', ['ip' => $row->ip_address]) }}" class="font-mono hover:underline">{{ $row->ip_address }}</a>
                                        <span class="font-semibold">{{ $row->total }}</span>
                                    </li>
                                @empty
                                    <li class="text-gray-500">—</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                @endif
            </section>

            {{-- Errors --}}
            <section id="bledy" class="{{ $card }}">
                <h3 class="font-semibold">Błędy aplikacji</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Pogrupowane według miejsca w kodzie. Komunikaty są czyszczone z adresów e-mail i numerów; błędy bazy danych pokazują tylko kod.</p>
                @if ($appErrors->isEmpty())
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">Brak zarejestrowanych błędów.</p>
                @else
                    <div class="mt-3 divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($appErrors as $error)
                            <div @class(['py-3 text-sm', 'opacity-50' => $error->resolved_at])>
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <span class="font-semibold">{{ class_basename($error->exception) }}</span>
                                        <span class="text-gray-500 dark:text-gray-400 font-mono text-xs break-all">{{ $error->file }}:{{ $error->line }}</span>
                                    </div>
                                    @if ($error->resolved_at)
                                        <span class="text-xs text-emerald-700 dark:text-emerald-400">rozwiązany {{ $error->resolved_at->format('d.m H:i') }}</span>
                                    @else
                                        <form method="POST" action="{{ route('admin.system.errors.resolve', $error) }}">
                                            @csrf
                                            <button class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Oznacz jako rozwiązany</button>
                                        </form>
                                    @endif
                                </div>
                                <div class="mt-1 text-gray-700 dark:text-gray-300 break-words">{{ $error->message }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $error->method }} {{ $error->url }} · ×{{ $error->occurrences }} ·
                                    pierwszy {{ $error->first_seen_at?->format('d.m.Y H:i') }}, ostatni {{ $error->last_seen_at?->format('d.m.Y H:i') }}
                                    @if ($error->user) · {{ $error->user->name }} @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- Failed jobs --}}
            @if ($failedJobs->isNotEmpty())
                <section class="{{ $card }}">
                    <h3 class="font-semibold">Nieudane zadania w tle</h3>
                    <div class="mt-3 divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                        @foreach ($failedJobs as $job)
                            <div class="py-2">
                                <span class="font-medium">{{ $job->name }}</span>
                                <span class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($job->failed_at)->format('d.m.Y H:i') }}</span>
                                <div class="text-gray-600 dark:text-gray-400 break-words">{{ $job->reason }}</div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Settings --}}
            <form method="POST" action="{{ route('admin.system.update') }}" class="{{ $card }} space-y-4">
                @csrf
                @method('PUT')
                <h3 class="font-semibold">Alerty i raporty</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Odbiorcy: {{ implode(', ', $recipients) ?: '—' }} (wszyscy administratorzy i dodatkowy adres poniżej).
                    @unless ($mailConfigured)
                        <strong class="text-red-700 dark:text-red-400">Poczta nie jest skonfigurowana — nic nie wyjdzie.</strong>
                    @endunless
                </p>

                @foreach (['alerts_enabled' => ['monitoring.alerts_enabled', 'Alerty e-mail od razu: seria nieudanych logowań, blokada, logowanie administratora z nowego IP, reset lub zmiana hasła, zmiana ustawień, błąd aplikacji'], 'daily_report' => ['monitoring.daily_report', 'Raport dzienny o 7:00 (stan systemu, zdarzenia bezpieczeństwa, błędy)']] as $name => [$key, $label])
                    <input type="hidden" name="{{ $name }}" value="0">
                    <label class="flex items-start gap-2 text-sm">
                        <input type="checkbox" name="{{ $name }}" value="1" class="mt-0.5 rounded border-gray-300 dark:border-gray-700 text-indigo-600"
                               @checked(old($name, $settings->get($key, '1') === '1'))>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="alert_email" value="Dodatkowy adres do alertów (opcjonalnie)" />
                        <input id="alert_email" name="alert_email" type="email" value="{{ old('alert_email', $settings->get('monitoring.alert_email')) }}" class="{{ $input }}">
                        <x-input-error :messages="$errors->get('alert_email')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="healthcheck_url" value="Adres pingu Healthchecks.io (opcjonalnie)" />
                        <input id="healthcheck_url" name="healthcheck_url" type="url" placeholder="https://hc-ping.com/…" value="{{ old('healthcheck_url', $settings->get('monitoring.healthcheck_url')) }}" class="{{ $input }}">
                        <x-input-error :messages="$errors->get('healthcheck_url')" class="mt-2" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Co 5 minut wysyłamy sygnał. Ustaw w Healthchecks okres 5 min i tolerancję 10 min — gdy sygnał przestanie przychodzić, dostaniesz alert z zewnątrz, nawet gdy cały serwer leży.</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <x-primary-button>Zapisz</x-primary-button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.system.test-alert') }}">
                @csrf
                <x-secondary-button type="submit">Wyślij alert testowy</x-secondary-button>
            </form>
        </div>
    </div>
</x-app-layout>
