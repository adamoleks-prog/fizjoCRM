@php
    $input = 'mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm text-sm';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Dziennik dostępu do danych pacjentów</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <p class="text-sm text-gray-600 dark:text-gray-400 px-4 sm:px-0">
                Kto i kiedy otworzył kartę pacjenta, zmienił dane, pobrał dokument, wysłał dane do asystenta albo kartę wizyty.
                Odpowiedź na pytanie pacjenta z RODO „kto miał wgląd w moje dane” — wpisz jego nazwisko. Wpisy nie są usuwane.
            </p>

            <form method="GET" class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-4 grid grid-cols-2 md:grid-cols-6 gap-3 items-end text-gray-900 dark:text-gray-100">
                <div class="col-span-2">
                    <label class="text-xs font-medium" for="patient">Pacjent (imię i/lub nazwisko)</label>
                    <input id="patient" name="patient" value="{{ $filters['patient'] ?? '' }}" class="{{ $input }}">
                </div>
                <div>
                    <label class="text-xs font-medium" for="user_id">Użytkownik</label>
                    <select id="user_id" name="user_id" class="{{ $input }}">
                        <option value="">wszyscy</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((int) ($filters['user_id'] ?? 0) === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium" for="action">Czynność</label>
                    <select id="action" name="action" class="{{ $input }}">
                        <option value="">każda</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action->value }}" @selected(($filters['action'] ?? null) === $action->value)>{{ $action->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2 col-span-2">
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
                    <a href="{{ route('admin.access-log.index') }}" class="self-center text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Wyczyść</a>
                </div>
            </form>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-x-auto text-gray-900 dark:text-gray-100">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 text-left text-xs text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2">Czas</th>
                            <th class="px-4 py-2">Użytkownik</th>
                            <th class="px-4 py-2">Czynność</th>
                            <th class="px-4 py-2">Pacjent</th>
                            <th class="px-4 py-2">Adres IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($logs as $log)
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $log->created_at?->format('d.m.Y H:i:s') }}</td>
                                <td class="px-4 py-2">{{ $log->user?->name ?? '—' }}</td>
                                <td class="px-4 py-2">{{ $log->action->label() }}</td>
                                <td class="px-4 py-2">
                                    @if ($log->patient)
                                        <a href="{{ route('admin.access-log.index', ['patient_id' => $log->patient_id]) }}" class="hover:underline">{{ $log->patient->last_name }} {{ $log->patient->first_name }}</a>
                                        @if ($log->patient->deleted_at) <span class="text-xs text-gray-500">(usunięty)</span> @endif
                                    @else
                                        #{{ $log->patient_id }}
                                    @endif
                                </td>
                                <td class="px-4 py-2 font-mono text-xs">{{ $log->ip_address }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Brak wpisów.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 sm:px-0">{{ $logs->links() }}</div>
        </div>
    </div>
</x-app-layout>
