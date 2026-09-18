<?php

namespace App\Models;

use App\Enums\BodyView;
use App\Models\Scopes\OperatorScope;
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
}
