<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Pacjenci</h2>
            <a href="{{ route('patients.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white">
                Dodaj pacjenta
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-4 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-lg">
                    {{ session('status') }}
                </div>
            @endif

            <form method="GET" class="flex gap-2">
                <x-text-input name="search" value="{{ request('search') }}" placeholder="Szukaj: nazwisko, imię, telefon" class="w-full" />
                <x-primary-button>Szukaj</x-primary-button>
            </form>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <table class="w-full text-sm text-left text-gray-900 dark:text-gray-100">
                    <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3">Nazwisko i imię</th>
                            <th class="px-6 py-3">Telefon</th>
                            <th class="px-6 py-3">Data urodzenia</th>
                            @if (auth()->user()->isAdmin())
                                <th class="px-6 py-3">Operator</th>
                            @endif
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($patients as $patient)
                            <tr class="border-b dark:border-gray-700">
                                <td class="px-6 py-4 font-medium">{{ $patient->last_name }} {{ $patient->first_name }}</td>
                                <td class="px-6 py-4">{{ $patient->phone ?? '—' }}</td>
                                <td class="px-6 py-4">{{ $patient->date_of_birth?->format('d.m.Y') ?? '—' }}</td>
                                @if (auth()->user()->isAdmin())
                                    <td class="px-6 py-4">{{ $patient->operator->name }}</td>
                                @endif
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('patients.show', $patient) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Karta pacjenta</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                    Brak pacjentów.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $patients->links() }}
        </div>
    </div>
</x-app-layout>
