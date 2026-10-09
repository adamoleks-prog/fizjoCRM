@php
    $input = 'mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm';
    $severity = [
        'info' => ['Info', 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'],
        'warning' => ['Ostrzeżenie', 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'],
        'critical' => ['Krytyczne', 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'],
    ];
    $detailLabels = [
        'account_exists' => 'konto istnieje',
        'failures_before' => 'nieudanych prób wcześniej',
        'new_ip' => 'nowy adres IP',
        'remember' => 'zapamiętaj mnie',
        'admin_area' => 'strefa administratora',
        'model' => 'rekord',
        'ids' => 'id',
        'area' => 'obszar',
        'changed' => 'zmienione pola',
        'previous' => 'poprzedni e-mail',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Dziennik bezpieczeństwa</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <p class="text-sm text-gray-600 dark:text-gray-400 px-4 sm:px-0">
                Logowania, nieudane próby, odmowy dostępu, skanowanie typowych adresów ataku, boty w zapisach online i zmiany ustawień.
                Bez haseł, kodów i treści formularzy. Przechowywane 12 miesięcy.
                Ataki na sam serwer (SSH, Webmin, FTP) widać w logach systemowych, nie tutaj.
            </p>

            @if ($summary['total'] > 0)
                <div class="flex flex-wrap gap-2 px-4 sm:px-0 text-xs">
                    <span class="text-gray-500 dark:text-gray-400 self-center">Ostatnie 24 h:</span>
                    @foreach ($summary['byType'] as $row)
                        <a href="{{ route('admin.security.index', ['type' => $row->type->value]) }}"
                           class="px-2 py-1 rounded bg-white dark:bg-gray-800 shadow-sm text-gray-700 dark:text-gray-300 hover:ring-1 hover:ring-indigo-400">
                            {{ $row->type->label() }}: <strong>{{ $row->total }}</strong>
                        </a>
                    @endforeach
                </div>
            @endif

            <form method="GET" class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-4 grid grid-cols-2 md:grid-cols-6 gap-3 items-end text-gray-900 dark:text-gray-100">
                <div class="col-span-2">
                    <label class="text-xs font-medium" for="type">Rodzaj</label>
                    <select id="type" name="type" class="{{ $input }}">
                        <option value="">wszystkie</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected(($filters['type'] ?? null) === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium" for="severity">Waga</label>
                    <select id="severity" name="severity" class="{{ $input }}">
                        <option value="">każda</option>
                        @foreach ($severity as $value => [$label])
                            <option value="{{ $value }}" @selected(($filters['severity'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium" for="ip">Adres IP</label>
                    <input id="ip" name="ip" value="{{ $filters['ip'] ?? '' }}" class="{{ $input }}">
                </div>
                <div>
                    <label class="text-xs font-medium" for="q">E-mail / adres strony</label>
                    <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="{{ $input }}">
                </div>
                <div class="grid grid-cols-2 gap-2 col-span-2 md:col-span-1">
                    <div>
                        <label class="text-xs font-medium" for="from">Od</label>
                        <input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="text-xs font-medium" for="to">Do</label>
                        <input id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="{{ $input }}">
                    </div>
                </div>
                <div class="col-span-2 md:col-span-6 flex gap-3">
                    <x-primary-button>Filtruj</x-primary-button>
                    <a href="{{ route('admin.security.index') }}" class="self-center text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Wyczyść</a>
                </div>
            </form>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-x-auto text-gray-900 dark:text-gray-100">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 text-left text-xs text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2">Czas</th>
                            <th class="px-4 py-2">Zdarzenie</th>
                            <th class="px-4 py-2">Konto / e-mail</th>
                            <th class="px-4 py-2">Adres IP</th>
                            <th class="px-4 py-2">Strona</th>
                            <th class="px-4 py-2">Szczegóły</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($events as $event)
                            <tr class="align-top">
                                <td class="px-4 py-2 whitespace-nowrap">{{ $event->created_at->format('d.m.Y H:i:s') }}</td>
                                <td class="px-4 py-2">
                                    <span class="text-[11px] font-semibold px-1.5 py-0.5 rounded {{ $severity[$event->severity][1] ?? '' }}">{{ $severity[$event->severity][0] ?? $event->severity }}</span>
                                    <span class="ml-1">{{ $event->type->label() }}</span>
                                </td>
                                <td class="px-4 py-2 break-all">
                                    {{ $event->user?->name ?? '' }}
                                    @if ($event->email && $event->email !== $event->user?->email)
                                        <span class="text-gray-500">{{ $event->email }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 font-mono text-xs whitespace-nowrap">
                                    <a href="{{ route('admin.security.index', ['ip' => $event->ip_address]) }}" class="hover:underline">{{ $event->ip_address }}</a>
                                </td>
                                <td class="px-4 py-2 font-mono text-xs break-all">{{ $event->method }} {{ $event->path }}</td>
                                <td class="px-4 py-2 text-xs text-gray-600 dark:text-gray-400">
                                    @foreach ($event->details ?? [] as $key => $value)
                                        <div>{{ $detailLabels[$key] ?? $key }}: {{ is_array($value) ? implode(', ', $value) : (is_bool($value) ? ($value ? 'tak' : 'nie') : $value) }}</div>
                                    @endforeach
                                    <div class="truncate max-w-xs" title="{{ $event->user_agent }}">{{ $event->user_agent }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Brak zdarzeń.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 sm:px-0">{{ $events->links() }}</div>
        </div>
    </div>
</x-app-layout>
