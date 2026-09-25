<?php

namespace App\Models;

use App\Models\Scopes\OperatorScope;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
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
    /** @use HasFactory<PatientFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'reminders_enabled' => 'boolean',
        ];
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
}
