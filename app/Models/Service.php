<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A kind of visit (physiotherapy, massage, …). Shared by the whole practice.
 */
#[Fillable(['name', 'duration_minutes', 'online_bookable', 'active', 'sort'])]
class Service extends Model
{
    protected function casts(): array
    {
        return [
            'online_bookable' => 'boolean',
            'is_default' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /** @param Builder<Service> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true)->orderBy('sort')->orderBy('name');
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->first();
    }
}
