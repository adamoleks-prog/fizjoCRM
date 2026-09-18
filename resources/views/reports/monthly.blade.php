<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Raport miesięczny — {{ $month->translatedFormat('F Y') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="GET" class="flex flex-wrap gap-2 items-end">
                <div>
                    <x-input-label for="month" value="Miesiąc" />
                    <x-text-input id="month" name="month" type="month" class="mt-1" value="{{ $month->format('Y-m') }}" />
                </div>

                @if ($operators->isNotEmpty())
                    <div>
                        <x-input-label for="operator_id" value="Operator" />
                        <select id="operator_id" name="operator_id"
                                class="block mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                            <option value="">Wszyscy</option>
                            @foreach ($operators as $operator)
                                <option value="{{ $operator->id }}" @selected($selectedOperatorId == $operator->id)>{{ $operator->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <x-primary-button>Pokaż</x-primary-button>
            </form>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach ($statuses as $status)
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-4 text-gray-900 dark:text-gray-100">
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $status->label() }}</div>
                        <div class="text-2xl font-semibold">{{ $countsByStatus[$status->value] ?? 0 }}</div>
                    </div>
                @endforeach
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <table class="w-full text-sm text-left text-gray-900 dark:text-gray-100">
                    <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3">Termin</th>
                            <th class="px-6 py-3">Pacjent</th>
                            @if ($operators->isNotEmpty())
                                <th class="px-6 py-3">Operator</th>
                            @endif
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">ICD-10</th>
                            <th class="px-6 py-3">Zabiegi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($appointments as $appointment)
                            <tr class="border-b dark:border-gray-700">
                                <td class="px-6 py-3 whitespace-nowrap">{{ $appointment->starts_at->format('d.m.Y H:i') }}</td>
                                <td class="px-6 py-3">{{ $appointment->patient->last_name }} {{ $appointment->patient->first_name }}</td>
                                @if ($operators->isNotEmpty())
                                    <td class="px-6 py-3">{{ $appointment->operator->name }}</td>
                                @endif
                                <td class="px-6 py-3">{{ $appointment->status->label() }}</td>
                                <td class="px-6 py-3">{{ $appointment->icd10_code ?? '—' }}</td>
                                <td class="px-6 py-3">{{ $appointment->procedures ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                    Brak wizyt w wybranym miesiącu.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($appointments->isNotEmpty())
                        <tfoot class="bg-gray-50 dark:bg-gray-700 font-semibold">
                            <tr>
                                <td class="px-6 py-3" colspan="{{ $operators->isNotEmpty() ? 6 : 5 }}">
                                    Łącznie wizyt: {{ $appointments->count() }}
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
