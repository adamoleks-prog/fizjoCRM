<?php

namespace App\Http\Requests;

use App\Models\Patient;
use App\Support\PersonalData;
use Illuminate\Foundation\Http\FormRequest;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Patient::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => PersonalData::firstName(),
            'last_name' => PersonalData::lastName(),
            'phone' => PersonalData::phone(required: false, mobileOnly: false),
            'email' => PersonalData::email(),
            'reminders_enabled' => ['boolean'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'operator_id' => [
                $this->user()->isAdmin() ? 'required' : 'nullable',
                'integer',
                'exists:users,id',
            ],
        ];
    }

    /**
     * Accepts the number in any common form; validation sees the nine digits.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => PersonalData::nationalDigits($this->input('phone')),
            'first_name' => is_string($this->input('first_name')) ? trim($this->input('first_name')) : $this->input('first_name'),
            'last_name' => is_string($this->input('last_name')) ? trim($this->input('last_name')) : $this->input('last_name'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return PersonalData::messages();
    }
}
