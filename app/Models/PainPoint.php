<?php

namespace App\Models;

use App\Enums\BodyView;
use App\Models\Concerns\EncryptsPatientData;
use App\Models\Scopes\OperatorScope;
use App\Services\Encryption\PatientKeyring;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A spot the patient pointed to during the interview, marked on the body chart.
 */
#[ScopedBy([OperatorScope::class])]
#[Fillable(['body_view', 'position_x', 'position_y', 'note'])]
class PainPoint extends Model
{
    use EncryptsPatientData;

    protected function casts(): array
    {
        return [
            'body_view' => BodyView::class,
            'position_x' => 'decimal:2',
            'position_y' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /**
     * Encrypted with the patient's own key (see EncryptsPatientData).
     *
     * @return list<string>
     */
    public function patientEncrypted(): array
    {
        return ['note'];
    }

    public function encryptionPatientId(): ?int
    {
        return app(PatientKeyring::class)->patientOfAppointment($this->appointment_id);
    }
}
