<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_offering_id',
        'name',
        'type',
        'weight_percentage',
        'weight_points',
        'weight_mode',
        'sort_order',
        'aggregation_mode',
        'drop_count',
    ];

    protected function casts(): array
    {
        return [
            'weight_percentage' => 'decimal:2',
            'weight_points' => 'decimal:2',
        ];
    }

    /**
     * Get the effective weight value based on the mode.
     */
    public function getEffectiveWeight(): float
    {
        return match ($this->weight_mode) {
            'points' => (float) ($this->weight_points ?? 0),
            default => (float) ($this->weight_percentage ?? 0),
        };
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class)->orderBy('sort_order');
    }
}
