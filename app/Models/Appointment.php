<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Models\Concerns\EncryptsPatientData;
use App\Models\Scopes\OperatorScope;
use App\Observers\AppointmentObserver;
use App\Services\Encryption\PatientKeyring;
use App\Services\Messaging\PhoneNumber;
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
    'service_id',
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
    use EncryptsPatientData;

    /** @use HasFactory<AppointmentFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<Model>|null measurements and pain points read with the previous patient's key */
    private ?array $childrenToReencrypt = null;

    protected static function booted(): void
    {
        // A visit moved to another patient's card: its measurements and pain
        // points follow, so they are read with the old key and written with the new.
        static::updating(function (Appointment $appointment) {
            if ($appointment->isDirty('patient_id')) {
                $appointment->childrenToReencrypt = [
                    ...$appointment->measurements()->withoutGlobalScopes()->get(),
                    ...$appointment->painPoints()->withoutGlobalScopes()->get(),
                ];
            }
        });

        static::updated(function (Appointment $appointment) {
            if ($appointment->childrenToReencrypt === null) {
                return;
            }

            app(PatientKeyring::class)->forgetAppointment($appointment->id);

            foreach ($appointment->childrenToReencrypt as $child) {
                $child->reencryptPatientData();
            }

            $appointment->childrenToReencrypt = null;
        });
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'booking_details' => 'array',
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
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** What the patient wrote about their problem when booking a first visit online. */
    public function reportedProblem(): ?string
    {
        $reason = $this->booking_details['reason'] ?? null;

        return filled($reason) ? $reason : null;
    }

    /** Booked online from a number other than the one on the patient's card. */
    public function bookedFromOtherPhone(): bool
    {
        return $this->booking_phone !== null
            && PhoneNumber::normalize($this->patient?->phone) !== $this->booking_phone;
    }

    /** Visits from before services existed count as the default (physiotherapy). */
    public function serviceName(): string
    {
        return $this->service?->name ?? Service::default()?->name ?? 'Wizyta';
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

    /**
     * Encrypted with the patient's own key (see EncryptsPatientData).
     *
     * @return list<string>
     */
    public function patientEncrypted(): array
    {
        return ['icd10_code', 'interview', 'examination', 'detailed_examination', 'conclusions', 'procedures', 'treatment_notes', 'internal_notes', 'patient_recommendations', 'booking_details'];
    }

    public function encryptionPatientId(): ?int
    {
        return $this->patient_id;
    }
}
