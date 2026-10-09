<?php

namespace App\Models;

use App\Models\Concerns\EncryptsPatientData;
use App\Models\Scopes\OperatorScope;
use App\Services\Messaging\PhoneNumber;
use App\Support\PersonalData;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[ScopedBy([OperatorScope::class])]
#[Fillable(['first_name', 'last_name', 'phone', 'email', 'reminders_enabled', 'date_of_birth', 'address', 'notes'])]
class Patient extends Model
{
    use EncryptsPatientData;

    /** @use HasFactory<PatientFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'erased_at' => 'datetime',
            'date_of_birth' => 'date',
            'reminders_enabled' => 'boolean',
        ];
    }

    /** Polish numbers are kept as "+48 602 118 940", however they were typed. */
    protected function phone(): Attribute
    {
        return Attribute::make(set: function (?string $value) {
            $digits = PersonalData::nationalDigits($value);

            return $digits !== null && strlen($digits) === 9 ? PhoneNumber::format('48'.$digits) : $value;
        });
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * @return HasMany<PatientComorbidity, $this>
     */
    public function comorbidities(): HasMany
    {
        return $this->hasMany(PatientComorbidity::class);
    }

    /**
     * Active conditions first, past ones last.
     *
     * @return Collection<int, PatientComorbidity>
     */
    public function orderedComorbidities(): Collection
    {
        return $this->comorbidities
            ->sortBy(fn (PatientComorbidity $c) => [$c->kind->sortOrder(), $c->id])
            ->values();
    }

    /**
     * @return HasMany<TherapyCycle, $this>
     */
    public function therapyCycles(): HasMany
    {
        return $this->hasMany(TherapyCycle::class);
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @return HasMany<PatientAccessLog, $this>
     */
    public function accessLogs(): HasMany
    {
        return $this->hasMany(PatientAccessLog::class);
    }

    /**
     * Encrypted with the patient's own key (see EncryptsPatientData).
     *
     * @return list<string>
     */
    public function patientEncrypted(): array
    {
        return ['address', 'notes'];
    }

    public function encryptionPatientId(): ?int
    {
        return $this->id;
    }
}
