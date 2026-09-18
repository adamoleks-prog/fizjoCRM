<?php

namespace App\Http\Requests;

use App\Enums\MeasurementType;
use App\Models\MeasurementTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreMeasurementTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', MeasurementTemplate::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', new Enum(MeasurementType::class)],
            'unit' => ['nullable', 'string', 'max:16'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nazwa pomiaru',
            'type' => 'typ pomiaru',
            'unit' => 'jednostka',
        ];
    }
}
