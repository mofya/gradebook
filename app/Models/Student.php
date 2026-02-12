<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = ['first_name', 'last_name', 'email'];

    public function courses()
    {
        return $this->belongsToMany(Course::class)->withTimestamps();
    }

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

    public function totalGradeForCourse($courseId)
    {
        $grades = $this->grades()->where('course_id', $courseId)->get();
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
