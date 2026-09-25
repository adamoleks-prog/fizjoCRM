<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Asystent terapii
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Wybierz cykl terapii, dla którego chcesz przygotować podpowiedź planu. W trakcie wizyty najszybciej otworzysz asystenta przyciskiem „Asystent terapii” na stronie wizyty — jeśli wizyta nie ma jeszcze cyklu, zostanie on założony.
            </p>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="w-full text-sm text-gray-900 dark:text-gray-100">
                    <thead class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">
                        <tr>
                            <th class="px-4 py-2 font-normal">Pacjent</th>
                            <th class="px-4 py-2 font-normal">Cykl</th>
                            <th class="px-4 py-2 font-normal">Ostatnia wizyta</th>
                            <th class="px-4 py-2 font-normal">Dane</th>
                            <th class="px-4 py-2 font-normal text-right">Podpowiedzi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($cycles as $cycle)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                <td class="px-4 py-2">{{ $cycle->patient?->last_name }} {{ $cycle->patient?->first_name }}</td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('therapy-cycles.show', $cycle) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $cycle->name }}</a>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    {{ $cycle->appointments_max_starts_at ? \Illuminate\Support\Carbon::parse($cycle->appointments_max_starts_at)->format('d.m.Y') : '—' }}
                                </td>
                                <td class="px-4 py-2">
                                    @if (! $cycle->clinicalCase)
                                        <span class="text-gray-400">nieprzygotowane</span>
                                    @elseif ($cycle->clinicalCase->isApproved())
                                        <span class="text-emerald-700 dark:text-emerald-400">zatwierdzone</span>
                                    @else
                                        <span class="text-amber-700 dark:text-amber-400">do przeglądu</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $cycle->recommendations_count }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                                    Nie ma jeszcze żadnego cyklu terapii. Otwórz wizytę i kliknij „Asystent terapii”.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $cycles->links() }}
        </div>
    </div>
</x-app-layout>
