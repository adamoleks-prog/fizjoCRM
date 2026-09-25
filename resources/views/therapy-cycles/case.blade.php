<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Przegląd danych cyklu przed wysłaniem: {{ $cycle->name }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <a href="{{ route('therapy-cycles.show', $cycle) }}"
               class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                &larr; Asystent terapii
            </a>

            @if (session('status'))
                <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 rounded-md p-3 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->has('approval') || $errors->has('acknowledge'))
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-300 rounded-md p-3 text-sm">
                    {{ $errors->first('approval') ?: $errors->first('acknowledge') }}
                </div>
            @endif

            <p class="text-sm text-gray-600 dark:text-gray-400">
                Sekcje „DOKUMENT” to zatwierdzone wcześniej wersje dokumentów — są dołączone bez zmian. Aby je poprawić, wróć do przeglądu danego dokumentu.
            </p>

            @include('review._gate', [
                'staleReason' => 'Od przygotowania zmieniła się dokumentacja wizyt, plan terapii albo zatwierdzenie któregoś dokumentu.',
                'routes' => [
                    'update' => route('clinical-cases.update', $cycle),
                    'approve' => route('clinical-cases.approve', $cycle),
                    'revoke' => route('clinical-cases.revoke', $cycle),
                    'regenerate' => route('clinical-cases.store', $cycle),
                ],
            ])
        </div>
    </div>
</x-app-layout>
