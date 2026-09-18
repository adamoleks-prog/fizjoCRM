<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Restricts queries to rows owned by the authenticated operator.
 *
 * Admins are unrestricted. Unauthenticated contexts (queue jobs, console commands)
 * are also unrestricted — they have no user to scope by and are trusted internals.
 */
class OperatorScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if ($user?->isOperator()) {
            $builder->where($model->qualifyColumn('operator_id'), $user->id);
        }
    }
}
