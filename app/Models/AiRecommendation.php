<?php

namespace App\Models;

use App\Models\Scopes\OperatorScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A therapy suggestion returned by the model. It is a hint for the
 * physiotherapist, never a decision: nothing here is written into the medical
 * record, the physiotherapist only marks whether it was useful.
 *
 * Every column is set by the request flow and the job, never from request input.
 */
#[ScopedBy([OperatorScope::class])]
class AiRecommendation extends Model
{
    protected function casts(): array
    {
        return [
            'response' => 'array',
            'cost' => 'decimal:6',
            'decided_at' => 'datetime',
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
     * @return BelongsTo<AiClinicalCase, $this>
     */
    public function clinicalCase(): BelongsTo
    {
        return $this->belongsTo(AiClinicalCase::class, 'ai_clinical_case_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function isQueued(): bool
    {
        return $this->status === 'queued';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
