<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('patient'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimetypes:'.implode(',', config('documents.allowed_mime')),
                'mimes:pdf',
                'max:'.config('documents.max_size_kb'),
            ],
            'appointment_id' => [
                'nullable',
                'integer',
                Rule::exists('appointments', 'id')->where('patient_id', $this->route('patient')->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'Dokument musi być plikiem PDF.',
            'file.mimetypes' => 'Dokument musi być plikiem PDF.',
            'file.max' => 'Dokument jest za duży (maks. '.round(config('documents.max_size_kb') / 1024).' MB).',
        ];
    }
}
