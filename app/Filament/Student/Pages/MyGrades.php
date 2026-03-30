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

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $title = 'My CA Results';

    protected static ?int $navigationSort = 1;

    public function getViewData(): array
    {
        $user = auth()->user();
        $gradingService = app(GradingService::class);

        $student = Student::findByEmail($user->email);

        if (! $student) {
            return ['courses' => collect(), 'student' => null];
        }

        $enrollments = Enrollment::query()
            ->where('student_id', $student->id)
            ->with([
                'courseOffering.course',
                'courseOffering.semester.year',
                'courseOffering.assessmentGroups.assessments.subsections',
                'courseOffering.gradingScheme.levels',
                'gradeResults.assessment',
                'gradeResults.subsectionScores.assessmentSubsection',
            ])
            ->get();

        $courses = $enrollments->map(function (Enrollment $enrollment) use ($gradingService) {
            $offering = $enrollment->courseOffering;
            $caWeight = (float) ($offering->ca_weight ?? 50);
            $scheme = $offering->gradingScheme;

            // CA total is on a 0-100 scale
            $caTotal = $enrollment->ca_total !== null ? (float) $enrollment->ca_total : null;

            // Weighted CA = ca_total * ca_weight / 100 (e.g., 80 * 40/100 = 32 out of 40)
            $weightedCa = $caTotal !== null ? round($caTotal * $caWeight / 100, 1) : null;

            // CA grade from the 0-100 percentage
            $caGrade = $caTotal !== null
                ? $gradingService->getLetterGradeFromScheme($caTotal, $scheme)
                : null;

            // Get only CA assessments (filter by CA groups)
            $caAssessments = $offering->assessmentGroups
                ->where('type', 'ca')
                ->flatMap(fn ($group) => $group->assessments)
                ->sortBy('sort_order');

            $gradeResultsByAssessment = $enrollment->gradeResults->keyBy('assessment_id');

            $assessmentRows = $caAssessments->map(function ($assessment) use ($gradeResultsByAssessment) {
                $result = $gradeResultsByAssessment->get($assessment->id);

                $subsections = [];
                if ($assessment->has_subsections && $result) {
                    $subsections = $result->subsectionScores->map(fn ($ss) => [
                        'name' => $ss->assessmentSubsection->name ?? 'Unknown',
                        'score' => (float) $ss->score,
                        'max_score' => (float) ($ss->assessmentSubsection->max_score ?? 100),
                    ])->values()->all();
                }

                return [
                    'name' => $assessment->name,
                    'max_score' => (float) $assessment->max_raw_score,
                    'raw_score' => $result?->raw_score !== null ? (float) $result->raw_score : null,
                    'is_excused' => $result?->is_excused ?? false,
                    'has_subsections' => $assessment->has_subsections && count($subsections) > 0,
                    'subsections' => $subsections,
                    'student_feedback' => $result?->student_feedback,
                ];
            })->values();

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
                'assessments' => $assessmentRows,
            ];
        });

        return [
            'student' => $student,
            'courses' => $courses,
        ];
    }
}
