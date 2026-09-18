<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Models\Patient;
use App\Rules\SlotAligned;
use App\Services\CollisionChecker;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Appointment::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $slot = (int) config('appointments.slot_minutes');

        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'starts_at' => ['required', 'date', app(SlotAligned::class)],
            'duration_minutes' => [
                'required',
                'integer',
                'min:'.$slot,
                'max:'.config('appointments.max_duration_minutes'),
                'multiple_of:'.$slot,
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // The patient must be visible to this user — the operator scope makes a
            // foreign patient invisible, which keeps booking inside own caseload.
            if (! $this->patient()) {
                $validator->errors()->add('patient_id', 'Wybrany pacjent jest niedostępny.');

                return;
            }

            if (app(CollisionChecker::class)->hasCollision(
                $this->operatorId(),
                $this->startsAt(),
                $this->endsAt(),
            )) {
                $validator->errors()->add('starts_at', 'Ten termin koliduje z inną wizytą.');
            }
        });
    }

    public function patient(): ?Patient
    {
        return once(fn () => Patient::find($this->integer('patient_id')));
    }

    /**
     * The appointment always belongs to the operator who owns the patient,
     * so an admin booking on someone's behalf does not capture the slot.
     */
    public function operatorId(): int
    {
        return $this->patient()->operator_id;
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
