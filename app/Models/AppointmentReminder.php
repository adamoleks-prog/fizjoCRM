<?php

namespace App\Models;

use App\Models\Scopes\OperatorScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to remind a patient of a visit. The message text is not kept.
 */
#[ScopedBy([OperatorScope::class])]
class AppointmentReminder extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function channelLabel(): string
    {
        return $this->channel === 'sms' ? 'SMS' : 'e-mail';
    }
}
