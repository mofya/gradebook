<?php

namespace App\Models;

use Database\Factories\UnmatchedLabGradeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnmatchedLabGrade extends Model
{
    /** @use HasFactory<UnmatchedLabGradeFactory> */
    use HasFactory;

    protected $fillable = [
        'course_offering_id',
        'assessment_id',
        'github_username',
        'row_data',
        'status',
        'matched_at',
        'matched_student_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'row_data' => 'array',
            'matched_at' => 'datetime',
        ];
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function matchedStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'matched_student_id');
    }
}
