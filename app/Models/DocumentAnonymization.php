<?php

namespace App\Models;

use App\Models\Scopes\OperatorScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The version of a document that may leave the server, kept separately from the
 * original — the original is medical record and must never be overwritten.
 */
#[ScopedBy([OperatorScope::class])]
#[Fillable(['anonymized_text', 'redaction_report', 'suspicion_count', 'confidence', 'status', 'ruleset_version'])]
class DocumentAnonymization extends Model
{
    protected function casts(): array
    {
        return [
            'redaction_report' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /** Nothing may be sent until a person has looked at it. */
    public function mayBeSent(): bool
    {
        return $this->isApproved();
    }
}
