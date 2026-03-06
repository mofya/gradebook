<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;

    protected $fillable = ['first_name', 'last_name', 'email', 'student_id_number', 'gender', 'program', 'year_of_study', 'study_mode', 'github_username'];

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class)
            ->withPivot('academic_year', 'semester')
            ->withTimestamps();
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function totalGradeForCourse(int $courseId): ?float
    {
        $grades = $this->grades()->where('course_id', $courseId)->with('assessment')->get();
        $totalWeight = 0;
        $weightedGrade = 0;

        foreach ($grades as $grade) {
            $weight = $grade->assessment->weight;
            $weightedGrade += $grade->grade * ($weight / 100);
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? ($weightedGrade / ($totalWeight / 100)) : null;
    }
}
