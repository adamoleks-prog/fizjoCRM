<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\TextExtractionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'patient_id',
    'appointment_id',
    'uploaded_by_user_id',
    'disk_path',
    'original_filename',
    'mime_type',
    'size_bytes',
    'type',
    'title',
])]
class Document extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'type' => DocumentType::class,
            'text_extraction_status' => TextExtractionStatus::class,
            'text_extracted_at' => 'datetime',
        ];
    }

    /** The name the physiotherapist gave it, falling back to the uploaded filename. */
    public function displayName(): string
    {
        return $this->title ?: $this->original_filename;
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
