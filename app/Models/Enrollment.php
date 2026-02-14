<?php

namespace App\Models;

use App\Enums\ExamStatus;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(\App\Observers\EnrollmentObserver::class)]
class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'course_offering_id',
        'source',
        'status',
        'study_mode',
        'exam_status',
        'ca_total',
        'ca_override',
        'ca_override_reason',
        'exam_score',
        'final_total',
        'final_override',
        'final_override_reason',
        'final_grade',
        'grade_points',
        'remarks',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'ca_total' => 'decimal:2',
            'ca_override' => 'decimal:2',
            'exam_score' => 'decimal:2',
            'final_total' => 'decimal:2',
            'final_override' => 'decimal:2',
            'grade_points' => 'decimal:1',
            'exam_status' => ExamStatus::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function gradeResults(): HasMany
    {
        return $this->hasMany(GradeResult::class);
    }

    /**
     * Get the effective CA total (override takes precedence).
     */
    public function getEffectiveCaTotal(): ?float
    {
        return $this->ca_override ?? $this->ca_total;
    }

    /**
     * Get the effective final total (override takes precedence).
     */
    public function getEffectiveFinalTotal(): ?float
    {
        return $this->final_override ?? $this->final_total;
    }
}
