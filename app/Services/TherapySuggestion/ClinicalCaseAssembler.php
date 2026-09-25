<?php

namespace App\Services\TherapySuggestion;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Document;
use App\Models\Patient;
use App\Models\Scopes\OperatorScope;
use App\Models\TherapyCycle;
use App\Models\TherapyMilestone;
use App\Services\Anonymization\DocumentAnonymizationService;
use App\Services\Anonymization\ReviewGate;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lays out the clinical picture of a therapy cycle as plain text, in a fixed order,
 * so the same records always produce the same text (and the same hash).
 *
 * Deliberately left out: the cycle name and document titles. Both are free text
 * typed by the physiotherapist and often carry the patient's surname
 * ("Rehabilitacja — Kowalska"), while adding nothing clinical.
 */
class ClinicalCaseAssembler
{
    /** Written sections of a visit, in the order of the examination card. */
    private const TEXT_FIELDS = [
        'Wywiad' => 'interview',
        'Badanie stanu funkcjonowania' => 'examination',
        'Badania szczegółowe' => 'detailed_examination',
        'Wnioski' => 'conclusions',
        'Wykonane zabiegi' => 'procedures',
        'Przebieg' => 'treatment_notes',
        'Notatka wewnętrzna' => 'internal_notes',
        'Zalecenia dla pacjenta' => 'patient_recommendations',
    ];

    public function __construct(private readonly ReviewGate $gate) {}

    public function assemble(TherapyCycle $cycle): ClinicalCaseSource
    {
        $patient = $this->patientOf($cycle);
        $visits = $this->documentedVisits($cycle);
        $documents = $this->approvedDocuments($patient);

        // Age and a plan alone are not a clinical picture worth sending.
        if ($visits->isEmpty() && $documents === '') {
            return new ClinicalCaseSource('', '');
        }

        return new ClinicalCaseSource($this->raw($cycle, $patient, $visits), $documents);
    }

    /**
     * @param  Collection<int, Appointment>  $visits
     */
    private function raw(TherapyCycle $cycle, Patient $patient, Collection $visits): string
    {
        $sections = [
            'PACJENT' => $this->demographics($patient),
            'CHOROBY WSPÓŁISTNIEJĄCE' => $this->comorbidities($patient),
            'PLAN TERAPII' => trim((string) $cycle->therapy_plan),
            'KROKI MILOWE' => $this->milestones($cycle),
        ];

        $blocks = [];

        foreach ($sections as $heading => $body) {
            if ($body !== '') {
                $blocks[] = "## {$heading}\n{$body}";
            }
        }

        foreach ($visits->values() as $index => $visit) {
            $blocks[] = $this->visit($index + 1, $visit);
        }

        return implode("\n\n", $blocks);
    }

    /** An age band rather than a birth date: the year of birth narrows who this is. */
    private function demographics(Patient $patient): string
    {
        if (! $patient->date_of_birth) {
            return 'Wiek: nieznany';
        }

        $age = $patient->date_of_birth->age;

        if ($age >= 90) {
            return 'Wiek: 90+ lat';
        }

        $from = intdiv($age, 5) * 5;

        return 'Wiek: '.$from.'–'.($from + 4).' lat';
    }

    private function comorbidities(Patient $patient): string
    {
        return $patient->comorbidities()->withoutGlobalScope(OperatorScope::class)->get()
            ->sortBy(fn ($c) => [$c->kind->sortOrder(), $c->id])
            ->map(fn ($c) => '- '.trim($c->name).' ('.mb_strtolower($c->kind->label()).')')
            ->implode("\n");
    }

    private function milestones(TherapyCycle $cycle): string
    {
        return $cycle->milestones()->withoutGlobalScope(OperatorScope::class)->get()
            ->sortBy(fn (TherapyMilestone $m) => [$m->horizon->sortOrder(), $m->id])
            ->map(fn (TherapyMilestone $m) => '- '.$m->horizon->label().': '.trim($m->goal)
                .($m->isAchieved() ? ' — osiągnięty '.$m->achieved_at->format('d.m.Y') : ' — w trakcie'))
            ->implode("\n");
    }

    /**
     * Visits with something written down — including the one in progress, so the
     * assistant can help straight after the interview. Cancelled and missed visits
     * are left out, as is a booked slot nobody has filled in yet.
     *
     * @return Collection<int, Appointment>
     */
    private function documentedVisits(TherapyCycle $cycle): Collection
    {
        return $cycle->appointments()
            ->withoutGlobalScope(OperatorScope::class)
            ->whereNotIn('status', [AppointmentStatus::Cancelled, AppointmentStatus::NoShow])
            ->with([
                'icd10',
                'measurements' => fn ($q) => $q->withoutGlobalScope(OperatorScope::class)->orderBy('id'),
                'measurements.template',
                'painPoints' => fn ($q) => $q->withoutGlobalScope(OperatorScope::class)->orderBy('id'),
            ])
            ->reorder('starts_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (Appointment $visit) => $this->hasContent($visit))
            ->values();
    }

    private function hasContent(Appointment $visit): bool
    {
        foreach (self::TEXT_FIELDS as $field) {
            if (filled($visit->{$field})) {
                return true;
            }
        }

        return filled($visit->icd10_code) || $visit->measurements->isNotEmpty() || $visit->painPoints->isNotEmpty();
    }

    private function visit(int $number, Appointment $visit): string
    {
        // A visit not yet marked as completed is usually the one happening right now.
        $marker = match (true) {
            $visit->status !== AppointmentStatus::Scheduled => '',
            $visit->starts_at->isToday() => ' (bieżąca, w trakcie)',
            default => ' (nieoznaczona jako odbyta)',
        };
        $lines = ["## WIZYTA {$number} — {$visit->starts_at->format('d.m.Y')}{$marker}"];

        if ($visit->icd10_code) {
            $lines[] = 'Rozpoznanie ICD-10: '.$visit->icd10_code.($visit->icd10Name() ? ' '.$visit->icd10Name() : '');
        }

        foreach (self::TEXT_FIELDS as $label => $field) {
            $value = $visit->{$field};

            if (filled($value)) {
                $lines[] = "{$label}:\n".trim(str_replace(["\r\n", "\r"], "\n", $value));
            }
        }

        if ($visit->measurements->isNotEmpty()) {
            $lines[] = "Pomiary:\n".$visit->measurements
                ->map(fn ($m) => '- '.$m->template->name.': '.$m->formattedValue().(filled($m->note) ? ' ('.trim($m->note).')' : ''))
                ->implode("\n");
        }

        if ($visit->painPoints->isNotEmpty()) {
            $lines[] = "Wizualizacja dolegliwości:\n".$visit->painPoints
                ->map(fn ($p) => '- '.$p->body_view->label().': '.(filled($p->note) ? trim($p->note) : 'bez opisu'))
                ->implode("\n");
        }

        return implode("\n", $lines);
    }

    /**
     * Documents a person has already approved, appended as they were approved.
     * Titles are replaced with the document type — a title may carry a name.
     */
    private function approvedDocuments(Patient $patient): string
    {
        $documents = Document::query()
            ->where('patient_id', $patient->id)
            ->with('anonymization')
            ->orderBy('id')
            ->get()
            // An approval of a stale version covers text nobody reviewed against
            // the current reading of the document, so it does not count.
            ->filter(fn (Document $d) => $d->type->isClinical()
                && $d->anonymization?->isApproved()
                && ! $this->gate->isStale($d->anonymization, DocumentAnonymizationService::sourceHash($d)))
            ->values();

        return $documents
            ->map(fn (Document $d, int $i) => '## DOKUMENT '.($i + 1).' ('.$d->type->label().")\n".trim($d->anonymization->anonymized_text))
            ->implode("\n\n");
    }

    /**
     * Resolved without the operator scope: the policy has already decided the caller
     * may see this cycle, and the queue worker has no user at all.
     */
    private function patientOf(TherapyCycle $cycle): Patient
    {
        return $cycle->patient()->withoutGlobalScope(OperatorScope::class)->firstOrFail();
    }
}
