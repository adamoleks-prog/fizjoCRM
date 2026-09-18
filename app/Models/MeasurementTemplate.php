<?php

namespace App\Models;

use App\Enums\MeasurementType;
use App\Models\Scopes\OperatorScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A repeatable measurement the physiotherapist defines once and then fills in
 * with a value on each visit.
 */
#[ScopedBy([OperatorScope::class])]
#[Fillable(['name', 'description', 'type', 'unit'])]
class MeasurementTemplate extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => MeasurementType::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /**
     * @return HasMany<Measurement, $this>
     */
    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }

    public function labelWithUnit(): string
    {
        return $this->unit ? "{$this->name} [{$this->unit}]" : $this->name;
    }
}
