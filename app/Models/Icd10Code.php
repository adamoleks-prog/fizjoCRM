<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'chapter'])]
class Icd10Code extends Model
{
    /**
     * Matches either the code (M54) or any part of the description, so the
     * physiotherapist can search by what they remember.
     *
     * Results are ranked because a bare LIKE '%rwa%' also matches the middle of
     * unrelated words ("nade-rwa-nie"), which would bury the obvious answer.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        return $query
            ->where(function (Builder $query) use ($term) {
                $query->where('code', 'like', $term.'%')
                    ->orWhere('name', 'like', '%'.$term.'%');
            })
            ->orderByRaw('CASE
                WHEN code = ? THEN 0
                WHEN code LIKE ? THEN 1
                WHEN name LIKE ? THEN 2
                WHEN name LIKE ? THEN 3
                ELSE 4
            END', [$term, $term.'%', $term.'%', '% '.$term.'%']);
    }
}
