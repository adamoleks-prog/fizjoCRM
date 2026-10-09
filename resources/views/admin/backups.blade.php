@php
    $card = 'bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100';
    $size = function (int $bytes) {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return number_format($bytes, $unit === 'B' ? 0 : 1, ',', ' ').' '.$unit;
            }
            $bytes /= 1024;
        }
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Kopie zapasowe</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 rounded-md p-3 text-sm">{{ session('status') }}</div>
            @endif

            <section class="{{ $card }}">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="text-sm space-y-1">
                        <p>
                            Ostatnia udana kopia:
                            <strong>{{ $lastSuccess ? $lastSuccess->format('d.m.Y H:i') : 'jeszcze nie było' }}</strong>
                        </p>
                        @if ($lastError)
                            <p class="text-red-700 dark:text-red-400">Ostatnia próba nieudana: {{ $lastError }}</p>
                        @endif
                        <p class="text-gray-600 dark:text-gray-400">
                            Codziennie o 2:30: cała baza danych i pliki pacjentów (dokumenty, podpisane zgody).
                            Przechowywane {{ $keepDays }} dni. Zaszyfrowane kluczem aplikacji (APP_KEY).
                        </p>
                    </div>
                    <form method="POST" action="{{ route('admin.backups.store') }}">
                        @csrf
                        <x-primary-button>Wykonaj kopię teraz</x-primary-button>
                    </form>
                </div>
            </section>

            <section class="{{ $card }}">
                <h3 class="font-semibold">Kopie na serwerze</h3>
                <div class="mt-3 divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                    @forelse ($backups as $backup)
                        <div class="py-2 flex flex-wrap items-center justify-between gap-3">
                            <span class="font-mono text-xs break-all">{{ $backup['name'] }}</span>
                            <span class="text-gray-500 dark:text-gray-400">{{ $backup['created_at']->format('d.m.Y H:i') }} · {{ $size($backup['size']) }}</span>
                            <a href="{{ route('admin.backups.download', $backup['name']) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Pobierz</a>
                        </div>
                    @empty
                        <p class="text-gray-600 dark:text-gray-400">Brak kopii.</p>
                    @endforelse
                </div>

                <h3 class="font-semibold mt-6">Kopie kluczy pacjentów</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Osobne klucze, którymi zaszyfrowana jest dokumentacja każdego pacjenta. Przechowywane tylko {{ $keysKeepDays }} dni —
                    dzięki temu po trwałym usunięciu pacjenta jego dane znikają także ze starszych kopii. Do odtworzenia potrzebna jest kopia danych <strong>i najnowsza kopia kluczy</strong>.
                </p>
                <div class="mt-2 divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                    @forelse ($keyFiles as $backup)
                        <div class="py-2 flex flex-wrap items-center justify-between gap-3">
                            <span class="font-mono text-xs break-all">{{ $backup['name'] }}</span>
                            <span class="text-gray-500 dark:text-gray-400">{{ $backup['created_at']->format('d.m.Y H:i') }} · {{ $size($backup['size']) }}</span>
                            <a href="{{ route('admin.backups.download', $backup['name']) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Pobierz</a>
                        </div>
                    @empty
                        <p class="text-gray-600 dark:text-gray-400">Brak.</p>
                    @endforelse
                </div>

                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Kopia zawiera dokumentację wszystkich pacjentów. Każde pobranie trafia do dziennika bezpieczeństwa i wysyła alert.
                    Pobraną kopię przechowuj na zaszyfrowanym dysku.
                </p>
            </section>

            <section class="{{ $card }}">
                <h3 class="font-semibold">Ważne</h3>
                <ul class="mt-2 text-sm list-disc pl-5 space-y-1 text-gray-700 dark:text-gray-300">
                    <li><strong>Bez klucza APP_KEY kopii nie da się otworzyć.</strong> Trzymaj go w menedżerze haseł, osobno od pobranych kopii. APP_KEY szyfruje też klucze pacjentów — jego utrata oznacza utratę całej dokumentacji.</li>
                    <li>Pobierając kopię na swój komputer, pobierz razem z nią najnowszą kopię kluczy — sama kopia danych bez kluczy pacjentów nie pozwoli odczytać dokumentacji.</li>
                    <li>Kopie leżą na tym samym serwerze co aplikacja — chronią przed błędem lub usunięciem danych, ale nie przed awarią całego serwera. Pobieraj co jakiś czas kopię na swój komputer albo podłącz zewnętrzny magazyn.</li>
                    <li>Po zmianie APP_KEY starsze kopie otwiera się starym kluczem.</li>
                </ul>

                <details class="mt-4 text-sm">
                    <summary class="cursor-pointer font-medium">Jak odtworzyć dane z kopii</summary>
                    <ol class="mt-2 list-decimal pl-5 space-y-2 text-gray-700 dark:text-gray-300">
                        <li>Odszyfruj (w miejsce KLUCZ wpisz całą wartość APP_KEY, razem z „base64:”):
                            <pre class="mt-1 p-2 bg-gray-100 dark:bg-gray-900 rounded text-xs overflow-x-auto">openssl enc -d -{{ config('backup.cipher') }} -pbkdf2 -iter {{ config('backup.iterations') }} -in PLIK{{ \App\Services\Backup\BackupRunner::EXTENSION }} -out kopia.tar.gz -pass pass:'KLUCZ'</pre>
                        </li>
                        <li>Tak samo odszyfruj <strong>najnowszy</strong> plik kluczy (<code>{{ \App\Services\Backup\BackupRunner::KEYS_PREFIX }}…{{ \App\Services\Backup\BackupRunner::KEYS_EXTENSION }}</code>) do <code>klucze.sql</code>.</li>
                        <li>Rozpakuj: <code>tar -xzf kopia.tar.gz</code> — powstaną <code>database.sql</code> i katalogi z plikami pacjentów.</li>
                        <li>Wczytaj najpierw dane, potem klucze: <code>mysql NAZWA_BAZY &lt; database.sql</code>, <code>mysql NAZWA_BAZY &lt; klucze.sql</code>; katalogi <code>patient-documents</code> i <code>private</code> skopiuj do <code>storage/app/</code>.</li>
                        <li>Pacjenci usunięci trwale po dacie kopii wrócą bez dokumentacji (nie mają już klucza) — usuń ich ponownie: <code>php artisan patient:erase NUMER</code>.</li>
                    </ol>
                </details>
            </section>
        </div>
    </div>
</x-app-layout>
