<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(\App\Observers\GradeResultObserver::class)]
class GradeResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'enrollment_id',
        'assessment_id',
        'graded_by',
        'raw_score',
        'normalized_score',
        'is_excused',
        'notes',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'raw_score' => 'decimal:2',
            'normalized_score' => 'decimal:2',
            'is_excused' => 'boolean',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function subsectionScores(): HasMany
    {
        return $this->hasMany(SubsectionScore::class);
    }

    /**
     * Calculate the normalized score based on assessment max_raw_score and normalized_to.
     */
    public function calculateNormalizedScore(): ?float
    {
        if ($this->raw_score === null || $this->is_excused) {
            return null;
        }

        $assessment = $this->assessment;

        if ($assessment->normalized_to !== null && $assessment->max_raw_score > 0) {
            return round(($this->raw_score / $assessment->max_raw_score) * $assessment->normalized_to, 2);
        }

        return $this->raw_score;
    }

    /**
     * Sum subsection scores and set raw_score to the total.
     */
    public function calculateFromSubsections(): float
    {
        $total = $this->subsectionScores()->sum('score');
        $this->update(['raw_score' => $total]);

        return (float) $total;
    }
}
