<?php

namespace App\Models;

use App\Models\Scopes\OperatorScope;
use App\Services\Anonymization\HasReviewState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The anonymised clinical picture of a therapy cycle — exactly the text that is
 * sent for a suggestion once a person has approved it.
 *
 * operator_id, therapy_cycle_id and the approval columns are not fillable: the
 * service assigns them explicitly, so they can never arrive from request input.
 */
#[ScopedBy([OperatorScope::class])]
#[Fillable([
    'anonymized_text',
    'generated_text',
    'source_hash',
    'redaction_report',
    'suspicion_count',
    'confidence',
    'status',
    'manual_edits',
    'ruleset_version',
])]
class AiClinicalCase extends Model
{
    use HasReviewState;

    protected function casts(): array
    {
        return [
            'redaction_report' => 'array',
            'approved_at' => 'datetime',
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
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * @return HasMany<AiRecommendation, $this>
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(AiRecommendation::class);
    }

    /** Fingerprint of the approved text, recorded with each request to prove what was sent. */
    public function textHash(): string
    {
        return hash('sha256', (string) $this->anonymized_text);
    }
}
