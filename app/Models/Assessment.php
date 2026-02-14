<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'weight',
        'course_id',
        'assessment_group_id',
        'max_raw_score',
        'normalized_to',
        'due_date',
        'has_subsections',
        'is_published',
        'published_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'max_raw_score' => 'decimal:2',
            'normalized_to' => 'decimal:2',
            'due_date' => 'date',
            'has_subsections' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function assessmentGroup(): BelongsTo
    {
        return $this->belongsTo(AssessmentGroup::class);
    }

    public function subsections(): HasMany
    {
        return $this->hasMany(AssessmentSubsection::class)->orderBy('sort_order');
    }
}
