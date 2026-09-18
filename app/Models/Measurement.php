<?php

namespace App\Models;

use App\Enums\MeasurementType;
use App\Models\Scopes\OperatorScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([OperatorScope::class])]
#[Fillable(['value_left', 'value_right', 'value_boolean', 'note'])]
class Measurement extends Model
{
    protected function casts(): array
    {
        return [
            'value_left' => 'decimal:2',
            'value_right' => 'decimal:2',
            'value_boolean' => 'boolean',
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
     * Includes soft deleted templates so a historic result keeps its label even
     * after the definition was removed from the library.
     *
     * @return BelongsTo<MeasurementTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(MeasurementTemplate::class, 'measurement_template_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function formattedValue(): string
    {
        $template = $this->template;
        $unit = $this->unitSuffix($template->unit);

        return match ($template->type) {
            MeasurementType::Boolean => $this->value_boolean ? 'Tak' : 'Nie',
            MeasurementType::Scale => $this->number($this->value_left).'/10',
            MeasurementType::Numeric => $this->number($this->value_left).$unit,
            MeasurementType::Bilateral => 'L: '.$this->number($this->value_left).$unit
                .'   P: '.$this->number($this->value_right).$unit,
        };
    }

    /** Symbols written tight to the number (120°), unlike cm or kg (46 cm). */
    private function unitSuffix(?string $unit): string
    {
        if ($unit === null) {
            return '';
        }

        return in_array($unit, ['°', '%'], true) ? $unit : ' '.$unit;
    }

    private function number(?string $value): string
    {
        if ($value === null) {
            return '—';
        }

        // Trailing zeros of the decimal cast read badly for whole numbers (90.00°).
        return rtrim(rtrim($value, '0'), '.') ?: '0';
    }
}
