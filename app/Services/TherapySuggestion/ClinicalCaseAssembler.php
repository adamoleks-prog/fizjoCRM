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
    public function __construct(private readonly ReviewGate $gate) {}

    public function assemble(TherapyCycle $cycle): ClinicalCaseSource
    {
        $patient = $this->patientOf($cycle);
        $visits = $this->completedVisits($cycle);
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
     * Only visits that took place — a booked or cancelled slot has no findings.
     *
     * @return Collection<int, Appointment>
     */
    private function completedVisits(TherapyCycle $cycle): Collection
    {
        return $cycle->appointments()
            ->withoutGlobalScope(OperatorScope::class)
            ->where('status', AppointmentStatus::Completed)
            ->with([
                'icd10',
                'measurements' => fn ($q) => $q->withoutGlobalScope(OperatorScope::class)->orderBy('id'),
                'measurements.template',
                'painPoints' => fn ($q) => $q->withoutGlobalScope(OperatorScope::class)->orderBy('id'),
            ])
            ->reorder('starts_at')
            ->orderBy('id')
            ->get();
    }

    private function visit(int $number, Appointment $visit): string
    {
        $lines = ["## WIZYTA {$number} — {$visit->starts_at->format('d.m.Y')}"];

        if ($visit->icd10_code) {
            $lines[] = 'Rozpoznanie ICD-10: '.$visit->icd10_code.($visit->icd10Name() ? ' '.$visit->icd10Name() : '');
        }

        $fields = [
            'Wywiad' => $visit->interview,
            'Badanie stanu funkcjonowania' => $visit->examination,
            'Badania szczegółowe' => $visit->detailed_examination,
            'Wnioski' => $visit->conclusions,
            'Wykonane zabiegi' => $visit->procedures,
            'Przebieg' => $visit->treatment_notes,
            'Notatka wewnętrzna' => $visit->internal_notes,
            'Zalecenia dla pacjenta' => $visit->patient_recommendations,
        ];

        foreach ($fields as $label => $value) {
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
