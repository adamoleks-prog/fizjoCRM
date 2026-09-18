<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnonymizedTextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('document'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'anonymized_text' => ['required', 'string', 'max:200000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'anonymized_text.required' => 'Tekst nie może być pusty. Aby zrezygnować z dokumentu, po prostu go nie zatwierdzaj.',
        ];
    }
}
