<?php

namespace App\Filament\Student\Pages;

use App\Models\Enrollment;
use App\Models\GradeQuery;
use App\Models\Student;
use App\Services\GradingService;
use Filament\Pages\Dashboard as BaseDashboard;

class StudentDashboard extends BaseDashboard
{
    protected string $view = 'filament.student.pages.student-dashboard';

    protected ?Student $cachedStudent = null;

    public function getViewData(): array
    {
        $user = auth()->user();
        $gradingService = app(GradingService::class);

        $student = $this->getStudent();

        if (! $student) {
            return [
                'student' => null,
                'courseCards' => collect(),
                'stats' => [],
                'openQueries' => 0,
            ];
        }

        $enrollments = Enrollment::query()
            ->where('student_id', $student->id)
            ->with([
                'courseOffering.course',
                'courseOffering.semester.year',
                'courseOffering.gradingScheme.levels',
            ])
            ->get();

        $openQueries = GradeQuery::where('student_id', $student->id)
            ->whereNotIn('status', ['resolved', 'rejected'])
            ->count();

        $courseCards = $enrollments->map(function (Enrollment $enrollment) use ($gradingService) {
            $offering = $enrollment->courseOffering;
            $caWeight = (float) ($offering->ca_weight ?? 50);
            $caTotal = $enrollment->ca_total !== null ? (float) $enrollment->ca_total : null;
            $weightedCa = $caTotal !== null ? round($caTotal * $caWeight / 100, 1) : null;
            $scheme = $offering->gradingScheme;
            $caGrade = $caTotal !== null
                ? $gradingService->getLetterGradeFromScheme($caTotal, $scheme)
                : null;

            $semester = $offering->semester;
            $semesterLabel = ($semester->year->name ?? '').' '.$semester->name;

            return [
                'course_code' => $offering->course->code,
                'course_name' => $offering->course->name,
                'semester' => $semesterLabel,
                'status' => $enrollment->status,
                'ca_weight' => $caWeight,
                'ca_total' => $caTotal,
                'weighted_ca' => $weightedCa,
                'ca_grade' => $caGrade,
            ];
        });

        $gradedCourses = $courseCards->filter(fn ($c) => $c['ca_total'] !== null);
        $overallAverage = $gradedCourses->isNotEmpty()
            ? round($gradedCourses->avg('ca_total'), 1)
            : null;

        return [
            'student' => $student,
            'courseCards' => $courseCards,
            'openQueries' => $openQueries,
            'stats' => [
                'enrolled_courses' => $courseCards->count(),
                'graded_courses' => $gradedCourses->count(),
                'overall_average' => $overallAverage,
            ],
        ];
    }

    protected function getStudent(): ?Student
    {
        if ($this->cachedStudent === null) {
            $this->cachedStudent = Student::findByEmail(auth()->user()->email);
        }

        return $this->cachedStudent;
    }
}
