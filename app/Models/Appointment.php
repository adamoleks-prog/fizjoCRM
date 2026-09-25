<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Models\Scopes\OperatorScope;
use App\Observers\AppointmentObserver;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([AppointmentObserver::class])]
#[ScopedBy([OperatorScope::class])]
#[Fillable([
    'patient_id',
    'starts_at',
    'ends_at',
    'status',
    'icd10_code',
    'interview',
    'detailed_examination',
    'conclusions',
    'procedures',
    'treatment_notes',
    'internal_notes',
    'patient_recommendations',
])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'status' => AppointmentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<TherapyCycle, $this>
     */
    public function therapyCycle(): BelongsTo
    {
        return $this->belongsTo(TherapyCycle::class);
    }

    /**
     * @return BelongsTo<Icd10Code, $this>
     */
    public function icd10(): BelongsTo
    {
        return $this->belongsTo(Icd10Code::class, 'icd10_code', 'code');
    }

    /**
     * The code is stored as plain text so historic records keep their diagnosis
     * even if the dictionary changes; the name is resolved for display only.
     */
    public function icd10Name(): ?string
    {
        return $this->icd10?->name;
    }

    /**
     * @return HasMany<Measurement, $this>
     */
    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }

    /**
     * @return HasMany<PainPoint, $this>
     */
    public function painPoints(): HasMany
    {
        return $this->hasMany(PainPoint::class);
    }

    /**
     * @return HasMany<AppointmentReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(AppointmentReminder::class)->latest('id');
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
