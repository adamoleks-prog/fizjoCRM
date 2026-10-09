<?php

namespace App\Models;

use App\Models\Concerns\EncryptsPatientData;
use App\Models\Scopes\OperatorScope;
use App\Services\Anonymization\HasReviewState;
use App\Services\Encryption\PatientKeyring;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The version of a document that may leave the server, kept separately from the
 * original — the original is medical record and must never be overwritten.
 *
 * operator_id, document_id and the approval columns are deliberately not fillable:
 * the service assigns them explicitly, so they can never arrive from request input.
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
class DocumentAnonymization extends Model
{
    use EncryptsPatientData, HasReviewState;

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

    /**
     * Encrypted with the patient's own key (see EncryptsPatientData).
     *
     * @return list<string>
     */
    public function patientEncrypted(): array
    {
        return ['anonymized_text', 'generated_text', 'redaction_report'];
    }

    public function encryptionPatientId(): ?int
    {
        return app(PatientKeyring::class)->patientOfDocument($this->document_id);
    }
}
