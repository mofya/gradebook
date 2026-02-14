<?php

namespace App\Filament\Student\Pages;

use App\Models\Enrollment;
use App\Models\Student;
use App\Services\GradingService;
use BackedEnum;
use Filament\Pages\Page;

class MyGrades extends Page
{
    protected string $view = 'filament.student.pages.my-grades';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $title = 'My Grades';

    protected static ?int $navigationSort = 1;

    public function getViewData(): array
    {
        $user = auth()->user();
        $gradingService = app(GradingService::class);

        $student = Student::query()
            ->where('email', $user->email)
            ->first();

        if (! $student) {
            return ['enrollments' => collect(), 'student' => null, 'cgpa' => 0.0, 'semesterGpas' => []];
        }

        $enrollments = Enrollment::query()
            ->where('student_id', $student->id)
            ->with([
                'courseOffering.course',
                'courseOffering.semester.year',
                'courseOffering.assessmentGroups.assessments',
                'gradeResults.assessment',
            ])
            ->get();

        $completedResults = $enrollments
            ->filter(fn ($e) => $e->final_total !== null && $e->courseOffering->is_published)
            ->map(fn ($e) => [
                'mark' => (float) $e->final_total,
                'credits' => $e->courseOffering->course->credits,
            ])
            ->values()
            ->toArray();

        $cgpa = $gradingService->calculateCumulativeGpa($completedResults);

        // Calculate semester GPAs
        $semesterGpas = [];
        $grouped = $enrollments->groupBy(fn ($e) => $e->courseOffering->semester_id);
        foreach ($grouped as $semesterId => $semesterEnrollments) {
            $semesterResults = $semesterEnrollments
                ->filter(fn ($e) => $e->final_total !== null && $e->courseOffering->is_published)
                ->map(fn ($e) => [
                    'mark' => (float) $e->final_total,
                    'credits' => $e->courseOffering->course->credits,
                ])
                ->values()
                ->toArray();

            $semester = $semesterEnrollments->first()->courseOffering->semester;
            $semesterGpas[$semesterId] = [
                'name' => ($semester->year->name ?? '').' '.$semester->name,
                'gpa' => $gradingService->calculateSemesterGpa($semesterResults),
            ];
        }

        return [
            'student' => $student,
            'enrollments' => $enrollments,
            'cgpa' => $cgpa,
            'semesterGpas' => $semesterGpas,
        ];
    }
}
