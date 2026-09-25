<?php

namespace App\Models;

use App\Models\Scopes\OperatorScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A consent wording. Placeholders filled in when the patient signs:
 * {PACJENT}, {DATA_URODZENIA}, {GABINET}, {FIZJOTERAPEUTA}, {DATA}.
 */
#[ScopedBy([OperatorScope::class])]
#[Fillable(['name', 'body'])]
class ConsentTemplate extends Model
{
    use SoftDeletes;

    public const PLACEHOLDERS = ['{PACJENT}', '{DATA_URODZENIA}', '{GABINET}', '{FIZJOTERAPEUTA}', '{DATA}'];

    public function render(Patient $patient, User $physiotherapist): string
    {
        return strtr($this->body, [
            '{PACJENT}' => $patient->first_name.' '.$patient->last_name,
            '{DATA_URODZENIA}' => $patient->date_of_birth?->format('d.m.Y') ?? '—',
            '{GABINET}' => $physiotherapist->practice_name ?: $physiotherapist->name,
            '{FIZJOTERAPEUTA}' => $physiotherapist->name,
            '{DATA}' => now()->format('d.m.Y'),
        ]);
    }
}
