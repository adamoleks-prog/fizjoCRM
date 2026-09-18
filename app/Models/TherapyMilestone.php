<?php

namespace App\Models;

use App\Enums\MilestoneHorizon;
use App\Models\Scopes\OperatorScope;
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
}
