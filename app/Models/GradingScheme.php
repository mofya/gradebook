<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingScheme extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'is_default', 'rounding_rule', 'decimal_places', 'rounding_precision', 'boundary_behavior'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function levels(): HasMany
    {
        return $this->hasMany(GradingSchemeLevel::class)->orderByDesc('min_mark');
    }

    /**
     * Get the letter grade for a given mark using this scheme's levels.
     */
    public function getLetterGrade(float $mark): string
    {
        $precision = $this->rounding_precision ?? $this->decimal_places ?? 0;

        $mark = match ($this->rounding_rule) {
            'floor' => floor($mark * (10 ** $precision)) / (10 ** $precision),
            'ceil' => ceil($mark * (10 ** $precision)) / (10 ** $precision),
            default => round($mark, $precision),
        };

        $levels = $this->levels->sortByDesc('min_mark')->values();
        $lastIndex = $levels->count() - 1;

        foreach ($levels as $index => $level) {
            $matches = match ($this->boundary_behavior) {
                'inclusive_upper' => $index === 0
                    ? ($mark >= $level->min_mark && $mark <= $level->max_mark)
                    : ($mark > $level->min_mark && $mark <= $level->max_mark),
                default => $index === $lastIndex // inclusive_lower (default)
                    ? ($mark >= $level->min_mark && $mark <= $level->max_mark)
                    : ($mark >= $level->min_mark && $mark < $level->max_mark + 1),
            };

            if ($matches) {
                return $level->letter;
            }
        }

        return 'D';
    }

    /**
     * Get grade points for a given mark.
     */
    public function getGradePoints(float $mark): float
    {
        $precision = $this->rounding_precision ?? $this->decimal_places ?? 0;
        $mark = round($mark, $precision);

        foreach ($this->levels as $level) {
            if ($mark >= $level->min_mark && $mark <= $level->max_mark) {
                return (float) $level->grade_points;
            }
        }

        return 0.0;
    }

    /**
     * Get the default grading scheme.
     */
    public static function getDefault(): ?self
    {
        return static::query()->where('is_default', true)->with('levels')->first();
    }
}
