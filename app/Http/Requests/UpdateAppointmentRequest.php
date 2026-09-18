<?php

namespace App\Http\Requests;

use App\Enums\AppointmentStatus;
use App\Enums\BodyView;
use App\Enums\ComorbidityKind;
use App\Enums\MeasurementType;
use App\Enums\MilestoneHorizon;
use App\Models\Appointment;
use App\Models\MeasurementTemplate;
use App\Rules\SlotAligned;
use App\Services\CollisionChecker;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->appointment());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $slot = (int) config('appointments.slot_minutes');

        return [
            'starts_at' => ['required', 'date', app(SlotAligned::class)],
            'duration_minutes' => [
                'required',
                'integer',
                'min:'.$slot,
                'max:'.config('appointments.max_duration_minutes'),
                'multiple_of:'.$slot,
            ],
            'status' => ['required', new Enum(AppointmentStatus::class)],
            'icd10_code' => ['nullable', 'string', 'max:16', 'exists:icd10_codes,code'],
            'interview' => ['nullable', 'string', 'max:5000'],
            'examination' => ['nullable', 'string', 'max:5000'],
            'detailed_examination' => ['nullable', 'string', 'max:5000'],
            'conclusions' => ['nullable', 'string', 'max:5000'],
            'procedures' => ['nullable', 'string', 'max:2000'],
            'treatment_notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'patient_recommendations' => ['nullable', 'string', 'max:5000'],
            // A cycle of the same patient necessarily belongs to the same operator,
            // and the appointment already passed the update policy — so matching the
            // patient is enough to keep this inside the caller's own caseload.
            'therapy_cycle_id' => [
                'nullable',
                'integer',
                Rule::exists('therapy_cycles', 'id')
                    ->where('patient_id', $this->appointment()->patient_id)
                    ->whereNull('deleted_at'),
            ],
            'new_therapy_cycle_name' => ['nullable', 'string', 'max:255'],

            'therapy_plan' => ['nullable', 'string', 'max:5000'],
            'milestones' => ['sometimes', 'array', 'max:50'],
            'milestones.*.id' => ['nullable', 'integer'],
            'milestones.*.goal' => ['nullable', 'string', 'max:500'],
            'milestones.*.horizon' => ['required_with:milestones.*.goal', new Enum(MilestoneHorizon::class)],
            'milestones.*.achieved' => ['nullable'],

            'pain_points' => ['sometimes', 'array', 'max:50'],
            'pain_points.*.body_view' => ['required_with:pain_points.*.position_x', new Enum(BodyView::class)],
            'pain_points.*.position_x' => ['nullable', 'numeric', 'between:0,100'],
            'pain_points.*.position_y' => ['nullable', 'numeric', 'between:0,100'],
            'pain_points.*.note' => ['nullable', 'string', 'max:255'],

            'comorbidities' => ['sometimes', 'array', 'max:30'],
            'comorbidities.*.id' => ['nullable', 'integer'],
            'comorbidities.*.name' => ['nullable', 'string', 'max:200'],
            'comorbidities.*.kind' => ['required_with:comorbidities.*.name', new Enum(ComorbidityKind::class)],

            'measurements' => ['sometimes', 'array', 'max:50'],
            'measurements.*.measurement_template_id' => ['nullable', 'integer', 'exists:measurement_templates,id'],
            'measurements.*.value_left' => ['nullable', 'numeric'],
            'measurements.*.value_right' => ['nullable', 'numeric'],
            'measurements.*.value_boolean' => ['nullable', 'boolean'],
            'measurements.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (app(CollisionChecker::class)->hasCollision(
                $this->appointment()->operator_id,
                $this->startsAt(),
                $this->endsAt(),
                $this->appointment()->id,
            )) {
                $validator->errors()->add('starts_at', 'Ten termin koliduje z inną wizytą.');
            }

            // A 0-10 measurement must stay inside its scale; other types have no
            // range to check, so this is the only value rule worth enforcing.
            foreach ($this->input('measurements', []) as $index => $row) {
                $template = MeasurementTemplate::find($row['measurement_template_id'] ?? null);
                $value = $row['value_left'] ?? null;

                if ($template?->type === MeasurementType::Scale && $value !== null && $value !== ''
                    && ($value < 0 || $value > 10)) {
                    $validator->errors()->add("measurements.{$index}.value_left", 'Wynik musi mieścić się w skali 0-10.');
                }
            }

            if ($this->integer('therapy_cycle_id') && $this->newTherapyCycleName() !== null) {
                $validator->errors()->add(
                    'therapy_cycle_id',
                    'Wybierz istniejący cykl albo podaj nazwę nowego — nie oba naraz.',
                );
            }
        });
    }

    /**
     * Null means no new cycle was requested; an empty string means one was
     * requested without a name, which the resolver fills in automatically.
     */
    public function newTherapyCycleName(): ?string
    {
        return $this->has('new_therapy_cycle_name')
            ? (string) $this->input('new_therapy_cycle_name')
            : null;
    }

    public function appointment(): Appointment
    {
        return $this->route('appointment');
    }

    public function startsAt(): Carbon
    {
        return Carbon::parse($this->input('starts_at'));
    }

    public function endsAt(): Carbon
    {
        return $this->startsAt()->addMinutes($this->integer('duration_minutes'));
    }
}
