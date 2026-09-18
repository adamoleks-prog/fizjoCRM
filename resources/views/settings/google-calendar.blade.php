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
                        Twój kalendarz Google jest połączony. Umówione wizyty są automatycznie dodawane
                        jako wydarzenia w Twoim kalendarzu.
                    </p>
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
