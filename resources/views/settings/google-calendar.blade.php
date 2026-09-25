<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Kalendarz Google</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-4 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-lg">
                    {{ session('status') }}
                </div>
            @endif

            <x-input-error :messages="$errors->get('google')" />

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                @if (! $configured)
                    <p class="text-sm text-amber-700 dark:text-amber-400">
                        Integracja nie jest skonfigurowana. Uzupełnij <code>GOOGLE_CLIENT_ID</code>,
                        <code>GOOGLE_CLIENT_SECRET</code> i <code>GOOGLE_REDIRECT_URI</code> w pliku <code>.env</code>.
                    </p>
                @elseif ($connected)
                    <p class="text-sm mb-4">
                        Twój kalendarz Google jest połączony. Trafiają do niego wizyty, w których jesteś
                        <strong>fizjoterapeutą prowadzącym</strong> — nie te, które tylko umówiłeś dla kogoś innego.
                    </p>
                    <div class="mb-6">
                        @if ($calendars === null)
                            <div class="text-sm bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-900 dark:text-amber-200 rounded-md p-3">
                                Aby wybrać, do którego kalendarza trafiają wizyty, połącz się ponownie — Google poprosi o zgodę na odczyt listy Twoich kalendarzy.
                                <a href="{{ route('google-calendar.redirect') }}" class="ml-1 font-semibold underline">Połącz ponownie</a>
                            </div>
                        @else
                            <form method="POST" action="{{ route('google-calendar.calendar') }}" class="flex flex-wrap items-end gap-3">
                                @csrf
                                @method('PUT')
                                <div>
                                    <x-input-label for="calendar_id" value="Dodawaj wizyty do kalendarza" />
                                    <select id="calendar_id" name="calendar_id"
                                            class="mt-1 block border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                                        @foreach ($calendars as $calendar)
                                            <option value="{{ $calendar['id'] }}" @selected($calendar['id'] === $currentCalendar || ($currentCalendar === 'primary' && $calendar['primary']))>
                                                {{ $calendar['name'] }}{{ $calendar['primary'] ? ' (główny)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <x-primary-button>Zapisz</x-primary-button>
                            </form>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                Nowy kalendarz (np. „Wizyty”) utworzysz w Google Calendar: Inne kalendarze → + → Utwórz nowy kalendarz. Po zmianie nadchodzące wizyty zostaną przeniesione.
                            </p>
                            <x-input-error :messages="$errors->get('calendar_id')" class="mt-2" />
                        @endif
                    </div>

                    <div class="mb-6 flex flex-wrap items-center gap-3 text-sm">
                        <span>Nadchodzące wizyty, które prowadzisz: <strong>{{ $upcoming }}</strong></span>
                        @if ($upcoming > 0)
                            <form method="POST" action="{{ route('google-calendar.sync') }}">
                                @csrf
                                <button class="text-indigo-600 dark:text-indigo-400 hover:underline">Wyślij je teraz do kalendarza</button>
                            </form>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('google-calendar.destroy') }}">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>Odłącz kalendarz</x-danger-button>
                    </form>
                @else
                    <p class="text-sm mb-4">
                        Połącz swoje konto Google, aby umówione wizyty trafiały automatycznie do Twojego kalendarza.
                    </p>
                    <a href="{{ route('google-calendar.redirect') }}"
                       class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white">
                        Połącz z Google Calendar
                    </a>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
