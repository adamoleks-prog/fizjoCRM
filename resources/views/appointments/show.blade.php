<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Wizyta {{ $appointment->starts_at->format('d.m.Y H:i') }}
            </h2>
            <a href="{{ route('appointments.edit', $appointment) }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white">
                Edytuj / uzupełnij
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-lg">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Pacjent</dt>
                        <dd><a href="{{ route('patients.show', $appointment->patient) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                            {{ $appointment->patient->last_name }} {{ $appointment->patient->first_name }}
                        </a></dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                        <dd>{{ $appointment->status->label() }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Termin</dt>
                        <dd>{{ $appointment->starts_at->format('d.m.Y H:i') }} – {{ $appointment->ends_at->format('H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Cykl terapeutyczny</dt>
                        <dd>
                            @if ($appointment->therapyCycle)
                                {{ $appointment->therapyCycle->name }}
                                · <a href="{{ route('therapy-cycles.show', $appointment->therapyCycle) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Asystent terapii</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Rozpoznanie ICD-10</dt>
                        <dd>
                            @if ($appointment->icd10_code)
                                <span class="font-mono font-semibold">{{ $appointment->icd10_code }}</span>
                                — {{ $appointment->icd10Name() ?? 'rozpoznanie spoza słownika' }}
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-gray-500 dark:text-gray-400">Wywiad</dt>
                        <dd class="whitespace-pre-line">{{ $appointment->interview ?? '—' }}</dd>
                    </div>
                    @if ($appointment->painPoints->isNotEmpty())
                        @php $numbered = $appointment->painPoints->values(); @endphp
                        <div class="md:col-span-2">
                            <dt class="text-gray-500 dark:text-gray-400">Wizualizacja dolegliwości</dt>
                            <dd class="mt-2 flex flex-col sm:flex-row gap-4">
                                @foreach (\App\Enums\BodyView::cases() as $bodyView)
                                    @php $inView = $numbered->where('body_view', $bodyView); @endphp
                                    @if ($inView->isNotEmpty())
                                        <div class="text-center">
                                            <div class="relative w-32">
                                                <x-body-silhouette class="text-gray-200 dark:text-gray-700" />
                                                @foreach ($inView as $point)
                                                    <div class="absolute -translate-x-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-rose-600 text-white text-[10px] font-semibold flex items-center justify-center"
                                                         style="left: {{ $point->position_x }}%; top: {{ $point->position_y }}%">
                                                        {{ $numbered->search($point) + 1 }}
                                                    </div>
                                                @endforeach
                                            </div>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $bodyView->label() }}</span>
                                        </div>
                                    @endif
                                @endforeach

                                <ul class="flex-1 space-y-1">
                                    @foreach ($numbered as $index => $point)
                                        <li class="flex items-start gap-2">
                                            <span class="shrink-0 w-5 h-5 rounded-full bg-rose-600 text-white text-[10px] font-semibold flex items-center justify-center">
                                                {{ $index + 1 }}
                                            </span>
                                            <span>
                                                {{ $point->note ?: 'bez opisu' }}
                                                <span class="text-xs text-gray-500 dark:text-gray-400">({{ $point->body_view->label() }})</span>
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </dd>
                        </div>
                    @endif

                    @if ($appointment->patient->comorbidities->isNotEmpty())
                        <div class="md:col-span-2">
                            <dt class="text-gray-500 dark:text-gray-400">Choroby współistniejące</dt>
                            <dd class="mt-1 flex flex-wrap gap-2">
                                @foreach ($appointment->patient->orderedComorbidities() as $comorbidity)
                                    <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                                        {{ $comorbidity->name }}
                                        <span class="text-gray-500 dark:text-gray-400">· {{ $comorbidity->kind->label() }}</span>
                                    </span>
                                @endforeach
                            </dd>
                        </div>
                    @endif

                    <div class="md:col-span-2">
                        <dt class="text-gray-500 dark:text-gray-400">Badanie stanu funkcjonowania</dt>
                        <dd class="whitespace-pre-line">{{ $appointment->examination ?? '—' }}</dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-gray-500 dark:text-gray-400">Badania szczegółowe</dt>
                        <dd class="whitespace-pre-line">{{ $appointment->detailed_examination ?? '—' }}</dd>
                    </div>
                    @if ($appointment->measurements->isNotEmpty())
                        <div class="md:col-span-2">
                            <dt class="text-gray-500 dark:text-gray-400">Pomiary</dt>
                            <dd>
                                <ul class="mt-1 space-y-1">
                                    @foreach ($appointment->measurements as $measurement)
                                        <li class="flex flex-wrap items-baseline gap-x-2">
                                            <span>{{ $measurement->template->name }}:</span>
                                            <span class="font-medium">{{ $measurement->formattedValue() }}</span>
                                            @if ($measurement->note)
                                                <span class="text-xs text-gray-500 dark:text-gray-400">({{ $measurement->note }})</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </dd>
                        </div>
                    @endif

                    <div class="md:col-span-2">
                        <dt class="text-gray-500 dark:text-gray-400">Wnioski z badania</dt>
                        <dd class="whitespace-pre-line">{{ $appointment->conclusions ?? '—' }}</dd>
                    </div>
                    @if ($cycle = $appointment->therapyCycle)
                        <div class="md:col-span-2">
                            <dt class="text-gray-500 dark:text-gray-400">Plan terapii</dt>
                            <dd class="whitespace-pre-line">{{ $cycle->therapy_plan ?? '—' }}</dd>

                            @if ($cycle->milestones->isNotEmpty())
                                <ul class="mt-2 space-y-1">
                                    @foreach ($cycle->orderedMilestones() as $milestone)
                                        <li class="flex items-start gap-2">
                                            <span class="shrink-0 text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                                {{ $milestone->horizon->label() }}
                                            </span>
                                            <span @class(['line-through text-gray-400 dark:text-gray-500' => $milestone->isAchieved()])>
                                                {{ $milestone->goal }}
                                            </span>
                                            @if ($milestone->isAchieved())
                                                <span class="text-xs text-emerald-600 dark:text-emerald-400 shrink-0">
                                                    osiągnięty {{ $milestone->achieved_at->format('d.m.Y') }}
                                                </span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif

                    <div class="md:col-span-2">
                        <dt class="text-gray-500 dark:text-gray-400">Zabiegi</dt>
                        <dd class="whitespace-pre-line">{{ $appointment->procedures ?? '—' }}</dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-gray-500 dark:text-gray-400">Przebieg wizyty</dt>
                        <dd class="whitespace-pre-line">{{ $appointment->treatment_notes ?? '—' }}</dd>
                    </div>

                    <div class="md:col-span-2 rounded-md bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-3">
                        <dt class="text-amber-800 dark:text-amber-300 font-medium">
                            Notatka wewnętrzna
                            <span class="font-normal">— nie trafia do wydruku dla pacjenta</span>
                        </dt>
                        <dd class="whitespace-pre-line mt-1">{{ $appointment->internal_notes ?? '—' }}</dd>
                    </div>

                    <div class="md:col-span-2 rounded-md bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 p-3">
                        <dt class="text-emerald-800 dark:text-emerald-300 font-medium">
                            Zalecenia dla pacjenta
                        </dt>
                        <dd class="whitespace-pre-line mt-1">{{ $appointment->patient_recommendations ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>
