<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $patient->last_name }} {{ $patient->first_name }}
            </h2>
            <a href="{{ route('patients.edit', $patient) }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white">
                Edytuj
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-lg">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                <h3 class="font-semibold mb-4">Dane pacjenta</h3>
                <dl class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div><dt class="text-gray-500 dark:text-gray-400">Telefon</dt><dd>{{ $patient->phone ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">E-mail</dt><dd>{{ $patient->email ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Data urodzenia</dt><dd>{{ $patient->date_of_birth?->format('d.m.Y') ?? '—' }}</dd></div>
                    <div class="md:col-span-2"><dt class="text-gray-500 dark:text-gray-400">Adres</dt><dd>{{ $patient->address ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Operator</dt><dd>{{ $patient->operator->name }}</dd></div>
                </dl>

                @if ($patient->notes)
                    <div class="mt-4 text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Uwagi</dt>
                        <dd class="whitespace-pre-line">{{ $patient->notes }}</dd>
                    </div>
                @endif

                <div class="mt-4 text-sm">
                    <dt class="text-gray-500 dark:text-gray-400">Choroby współistniejące</dt>
                    <dd class="mt-1">
                        @forelse ($patient->orderedComorbidities() as $comorbidity)
                            <span class="inline-block text-xs px-2 py-1 mr-2 mb-1 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                                {{ $comorbidity->name }}
                                <span class="text-gray-500 dark:text-gray-400">· {{ $comorbidity->kind->label() }}</span>
                            </span>
                        @empty
                            <span class="text-gray-500 dark:text-gray-400">—</span>
                        @endforelse
                    </dd>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <h3 class="font-semibold">Dokumenty</h3>
                    <a href="{{ route('consents.create', $patient) }}"
                       class="inline-flex items-center px-3 py-1.5 bg-indigo-600 rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-indigo-500">
                        Podpisz zgodę na tablecie
                    </a>
                </div>

                <form method="POST" action="{{ route('documents.store', $patient) }}" enctype="multipart/form-data"
                      data-document-scanner class="mb-4 space-y-2">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div>
                            <x-input-label for="type" value="Rodzaj dokumentu" />
                            <select id="type" name="type" required
                                    class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm text-sm">
                                @foreach (\App\Enums\DocumentType::cases() as $documentType)
                                    <option value="{{ $documentType->value }}" @selected(old('type', 'referral') === $documentType->value)>
                                        {{ $documentType->label() }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('type')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="title" value="Nazwa (opcjonalnie)" />
                            <x-text-input id="title" name="title" class="block mt-1 w-full text-sm" :value="old('title')"
                                          placeholder="np. Skierowanie od ortopedy" />
                            <x-input-error :messages="$errors->get('title')" class="mt-1" />
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
                        <input type="file" name="file" accept="application/pdf,image/*" capture="environment" required
                               class="text-sm text-gray-900 dark:text-gray-100 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-gray-800 dark:file:bg-gray-200 file:text-white dark:file:text-gray-800 file:text-xs file:uppercase file:font-semibold" />
                        <x-primary-button>Dodaj dokument</x-primary-button>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Zdjęcie z aparatu zostanie automatycznie zamienione na PDF. Maks. {{ round(config('documents.max_size_kb') / 1024) }} MB.
                    </p>
                    <p data-scanner-status class="text-xs text-indigo-600 dark:text-indigo-400"></p>
                    <x-input-error :messages="$errors->get('file')" class="mt-1" />
                </form>

                @forelse ($patient->documents as $document)
                    <div class="flex items-start justify-between gap-3 border-b dark:border-gray-700 py-2 text-sm last:border-0">
                        <div>
                            <a href="{{ route('documents.show', $document) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ $document->displayName() }}
                            </a>
                            <div class="mt-0.5 flex flex-wrap items-center gap-2">
                                <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                    {{ $document->type->label() }}
                                </span>
                                @php $extraction = $document->text_extraction_status; @endphp
                                <span @class([
                                    'text-xs',
                                    'text-emerald-600 dark:text-emerald-400' => $extraction->hasText(),
                                    'text-gray-500 dark:text-gray-400' => ! $extraction->hasText(),
                                ])>
                                    {{ $extraction->label() }}
                                </span>
                            </div>

                            @if ($document->type->isClinical() && $extraction->hasText())
                                @php $anonymization = $document->anonymization; @endphp
                                <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs">
                                    @if ($anonymization)
                                        <a href="{{ route('anonymizations.show', $document) }}"
                                           class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                            Przejrzyj przed analizą
                                        </a>
                                        <span @class([
                                            'text-emerald-600 dark:text-emerald-400' => $anonymization->isApproved(),
                                            'text-amber-600 dark:text-amber-400' => ! $anonymization->isApproved(),
                                        ])>
                                            {{ $anonymization->isApproved() ? 'zatwierdzone do analizy' : 'czeka na przegląd' }}
                                        </span>
                                    @else
                                        <form method="POST" action="{{ route('anonymizations.store', $document) }}">
                                            @csrf
                                            <button class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                                Przygotuj do analizy
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <span class="text-gray-500 dark:text-gray-400 shrink-0">
                            {{ $document->created_at->format('d.m.Y') }} · {{ round($document->size_bytes / 1024) }} KB
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Brak dokumentów.</p>
                @endforelse
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                <h3 class="font-semibold mb-4">Historia wizyt</h3>

                @forelse ($appointments as $appointment)
                    <div class="border-b dark:border-gray-700 py-3 text-sm last:border-0">
                        <div class="flex justify-between">
                            <span class="font-medium">{{ $appointment->starts_at->format('d.m.Y H:i') }}</span>
                            <span class="text-gray-500 dark:text-gray-400">{{ $appointment->status->label() }}</span>
                        </div>
                        @if ($cycle = $appointment->therapyCycle)
                            <div class="text-gray-600 dark:text-gray-300">Cykl: <a href="{{ route('therapy-cycles.show', $cycle) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $cycle->name }}</a></div>
                        @endif

                        @if ($appointment->icd10_code)
                            <div class="text-gray-600 dark:text-gray-300">
                                ICD-10: <span class="font-mono">{{ $appointment->icd10_code }}</span>
                                @if ($name = $appointment->icd10Name())
                                    — {{ $name }}
                                @endif
                            </div>
                        @endif
                        @if ($appointment->procedures)
                            <div class="text-gray-600 dark:text-gray-300">Zabiegi: {{ $appointment->procedures }}</div>
                        @endif
                        @if ($appointment->treatment_notes)
                            <div class="text-gray-600 dark:text-gray-300 whitespace-pre-line">{{ $appointment->treatment_notes }}</div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Brak wizyt.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
