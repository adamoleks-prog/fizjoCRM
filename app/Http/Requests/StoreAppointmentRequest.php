<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Models\Patient;
use App\Rules\SlotAligned;
use App\Services\CollisionChecker;
use App\Services\SlotService;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        // Before validation the patient may not exist yet — fall back to the caller,
        // whose schedule is then checked; the patient error is reported anyway.
        $operatorId = $this->patient()?->operator_id ?? $this->user()->id;
        $slots = app(SlotService::class);
        $slot = $slots->slotMinutes($operatorId);

        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('active', true)],
            'starts_at' => ['required', 'date', new SlotAligned($slots, $operatorId)],
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
