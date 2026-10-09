<?php

namespace App\Models;

use App\Enums\MilestoneHorizon;
use App\Models\Concerns\EncryptsPatientData;
use App\Models\Scopes\OperatorScope;
use App\Services\Encryption\PatientKeyring;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A goal on the way through a therapy cycle — short-term steps building up to the
 * long-term outcome.
 */
#[ScopedBy([OperatorScope::class])]
#[Fillable(['goal', 'horizon', 'achieved_at'])]
class TherapyMilestone extends Model
{
    use EncryptsPatientData;

    protected function casts(): array
    {
        return [
            'horizon' => MilestoneHorizon::class,
            'achieved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TherapyCycle, $this>
     */
    public function therapyCycle(): BelongsTo
    {
        return $this->belongsTo(TherapyCycle::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function isAchieved(): bool
    {
        return $this->achieved_at !== null;
    }

    /**
     * Encrypted with the patient's own key (see EncryptsPatientData).
     *
     * @return list<string>
     */
    public function patientEncrypted(): array
    {
        return ['goal'];
    }

    public function encryptionPatientId(): ?int
    {
        return app(PatientKeyring::class)->patientOfCycle($this->therapy_cycle_id);
    }
}
