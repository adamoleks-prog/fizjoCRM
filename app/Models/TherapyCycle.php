<?php

namespace App\Models;

use App\Models\Scopes\OperatorScope;
use Database\Factories\TherapyCycleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * Groups a patient's visits that belong to one course of treatment.
 */
#[ScopedBy([OperatorScope::class])]
#[Fillable(['patient_id', 'name', 'therapy_plan'])]
class TherapyCycle extends Model
{
    /** @use HasFactory<TherapyCycleFactory> */
    use HasFactory, SoftDeletes;

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
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class)->orderBy('starts_at');
    }

    /**
     * @return HasOne<AiClinicalCase, $this>
     */
    public function clinicalCase(): HasOne
    {
        return $this->hasOne(AiClinicalCase::class);
    }

    /**
     * @return HasMany<AiRecommendation, $this>
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(AiRecommendation::class)->latest();
    }

    /**
     * @return HasMany<TherapyMilestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(TherapyMilestone::class);
    }

    /**
     * Short-term goals first, because they are the steps toward the long-term one.
     *
     * @return Collection<int, TherapyMilestone>
     */
    public function orderedMilestones(): Collection
    {
        return $this->milestones->sortBy([
            fn (TherapyMilestone $a, TherapyMilestone $b) => $a->horizon->sortOrder() <=> $b->horizon->sortOrder(),
            fn (TherapyMilestone $a, TherapyMilestone $b) => $a->id <=> $b->id,
        ])->values();
    }

    /**
     * The diagnosis of the earliest visit that has one — derived rather than
     * stored, so there is no second copy to keep in sync with the visits.
     */
    public function primaryIcd10Code(): ?string
    {
        return $this->appointments()
            ->whereNotNull('icd10_code')
            ->reorder('starts_at')
            ->value('icd10_code');
    }

    public function primaryIcd10Name(): ?string
    {
        $code = $this->primaryIcd10Code();

        return $code ? Icd10Code::where('code', $code)->value('name') : null;
    }
}
