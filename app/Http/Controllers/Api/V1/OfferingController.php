<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\LabGradeImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferingController extends Controller
{
    /**
     * List course offerings with basic info.
     */
    public function index(): JsonResponse
    {
        $offerings = CourseOffering::query()
            ->with(['course', 'semester.year', 'lecturer'])
            ->paginate(20);

        return response()->json([
            'data' => $offerings->map(fn ($o) => [
                'id' => $o->id,
                'course_code' => $o->course->code,
                'course_name' => $o->course->name,
                'semester' => ($o->semester->year->name ?? '').' '.$o->semester->name,
                'lecturer' => $o->lecturer?->name,
                'status' => $o->status,
                'ca_weight' => $o->ca_weight,
                'exam_weight' => $o->exam_weight,
            ]),
            'meta' => [
                'current_page' => $offerings->currentPage(),
                'last_page' => $offerings->lastPage(),
                'total' => $offerings->total(),
            ],
        ]);
    }

    /**
     * Show a single course offering with enrollments and assessments.
     */
    public function show(CourseOffering $offering): JsonResponse
    {
        $offering->load([
            'course',
            'semester.year',
            'lecturer',
            'enrollments.student',
            'assessmentGroups.assessments',
        ]);

        return response()->json([
            'data' => [
                'id' => $offering->id,
                'course_code' => $offering->course->code,
                'course_name' => $offering->course->name,
                'semester' => ($offering->semester->year->name ?? '').' '.$offering->semester->name,
                'lecturer' => $offering->lecturer?->name,
                'status' => $offering->status,
                'ca_weight' => $offering->ca_weight,
                'exam_weight' => $offering->exam_weight,
                'enrollments' => $offering->enrollments->map(fn ($e) => [
                    'id' => $e->id,
                    'student_name' => $e->student->first_name.' '.$e->student->last_name,
                    'student_id' => $e->student->student_id_number,
                    'email' => $e->student->email,
                    'github_username' => $e->student->github_username,
                    'status' => $e->status,
                    'final_total' => $e->final_total,
                    'final_grade' => $e->final_grade,
                    'grade_points' => $e->grade_points,
                ]),
                'assessment_groups' => $offering->assessmentGroups->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'type' => $g->type,
                    'weight_percentage' => $g->weight_percentage,
                    'assessments' => $g->assessments->map(fn ($a) => [
                        'id' => $a->id,
                        'name' => $a->name,
                        'max_raw_score' => $a->max_raw_score,
                    ]),
                ]),
            ],
        ]);
    }

    /**
     * Get enrollments for an offering.
     */
    public function enrollments(CourseOffering $offering): JsonResponse
    {
        $offering->load('enrollments.student');

        return response()->json([
            'data' => $offering->enrollments->map(fn ($e) => [
                'id' => $e->id,
                'student_name' => $e->student->first_name.' '.$e->student->last_name,
                'student_id' => $e->student->student_id_number,
                'email' => $e->student->email,
                'github_username' => $e->student->github_username,
                'status' => $e->status,
                'exam_status' => $e->exam_status,
                'ca_total' => $e->ca_total,
                'exam_score' => $e->exam_score,
                'final_total' => $e->final_total,
                'final_grade' => $e->final_grade,
                'grade_points' => $e->grade_points,
                'remarks' => $e->remarks,
            ]),
        ]);
    }

    /**
     * Get grade results for an offering (enhanced with subsections).
     */
    public function grades(CourseOffering $offering): JsonResponse
    {
        $offering->load([
            'enrollments.gradeResults.assessment',
            'enrollments.gradeResults.subsectionScores.assessmentSubsection',
            'enrollments.student',
        ]);

        $results = $offering->enrollments->flatMap(function ($enrollment) {
            return $enrollment->gradeResults->map(fn ($gr) => [
                'student_id' => $enrollment->student->student_id_number,
                'student_name' => $enrollment->student->first_name.' '.$enrollment->student->last_name,
                'github_username' => $enrollment->student->github_username,
                'assessment' => $gr->assessment->name,
                'raw_score' => $gr->raw_score,
                'normalized_score' => $gr->normalized_score,
                'is_excused' => $gr->is_excused,
                'subsections' => $gr->subsectionScores->map(fn ($ss) => [
                    'name' => $ss->assessmentSubsection->name,
                    'score' => $ss->score,
                ]),
                'student_feedback' => $gr->student_feedback,
                'graded_at' => $gr->updated_at?->toIso8601String(),
            ]);
        });

        return response()->json(['data' => $results->values()]);
    }

    /**
     * Bulk import lab grades via JSON.
     */
    public function importLabGrades(Request $request, CourseOffering $offering): JsonResponse
    {
        $validated = $request->validate([
            'assessment_name' => 'required|string',
            'grades' => 'required|array|min:1',
            'grades.*.github_username' => 'required|string',
            'grades.*.final_score' => 'required|numeric|min:0|max:100',
            'grades.*.visible_tests' => 'nullable|numeric|min:0|max:100',
            'grades.*.hidden_tests' => 'nullable|numeric|min:0|max:100',
            'grades.*.code_quality' => 'nullable|numeric|min:0|max:100',
            'grades.*.plagiarism_flag' => 'nullable|string',
            'grades.*.student_feedback' => 'nullable|string',
            'grades.*.instructor_notes' => 'nullable|string',
        ]);

        // Find or create the assessment
        $assessment = $this->resolveAssessment($offering, $validated['assessment_name']);

        // Convert JSON grades to the CSV row format that LabGradeImportService expects
        $rows = collect($validated['grades'])->map(fn ($grade) => [
            'GitHub Username' => $grade['github_username'],
            'Final Score (%)' => (string) $grade['final_score'],
            'Visible Tests (%)' => (string) ($grade['visible_tests'] ?? 0),
            'Hidden Tests (%)' => (string) ($grade['hidden_tests'] ?? 0),
            'Code Quality (%)' => (string) ($grade['code_quality'] ?? 0),
            'Student Feedback' => $grade['student_feedback'] ?? '',
            'Plagiarism Note' => ($grade['plagiarism_flag'] ?? '') === 'Yes'
                ? 'Plagiarism flagged.'
                : '',
            'Instructor Notes' => $grade['instructor_notes'] ?? '',
        ])->all();

        $service = app(LabGradeImportService::class);
        $stats = $service->import($offering, $assessment, $rows);

        return response()->json([
            'message' => 'Lab grades imported successfully.',
            'data' => $stats,
        ]);
    }

    /**
     * Get a specific student's grades for an offering by external identifier.
     */
    public function studentGrades(CourseOffering $offering, string $identifier): JsonResponse
    {
        $offering->load(['course', 'semester.year']);

        // Try student_id_number first, then github_username
        $student = Student::where('student_id_number', $identifier)->first()
            ?? Student::whereRaw('LOWER(github_username) = ?', [strtolower($identifier)])->first();

        if (! $student) {
            return response()->json(['error' => "Student not found: {$identifier}"], 404);
        }

        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('course_offering_id', $offering->id)
            ->first();

        if (! $enrollment) {
            return response()->json([
                'error' => "Student {$identifier} is not enrolled in this offering.",
            ], 404);
        }

        $enrollment->load([
            'gradeResults.assessment',
            'gradeResults.subsectionScores.assessmentSubsection',
        ]);

        return response()->json([
            'data' => [
                'student' => [
                    'student_id_number' => $student->student_id_number,
                    'name' => $student->first_name.' '.$student->last_name,
                    'email' => $student->email,
                    'github_username' => $student->github_username,
                ],
                'offering' => [
                    'course_code' => $offering->course->code,
                    'semester' => ($offering->semester->year->name ?? '').' '.$offering->semester->name,
                ],
                'ca_total' => $enrollment->ca_total,
                'exam_score' => $enrollment->exam_score,
                'final_total' => $enrollment->final_total,
                'final_grade' => $enrollment->final_grade,
                'assessments' => $enrollment->gradeResults->map(fn ($gr) => [
                    'name' => $gr->assessment->name,
                    'raw_score' => $gr->raw_score,
                    'normalized_score' => $gr->normalized_score,
                    'subsections' => $gr->subsectionScores->map(fn ($ss) => [
                        'name' => $ss->assessmentSubsection->name,
                        'score' => $ss->score,
                    ]),
                    'student_feedback' => $gr->student_feedback,
                    'graded_at' => $gr->updated_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    /**
     * Find or create an assessment for the offering.
     */
    private function resolveAssessment(CourseOffering $offering, string $name): Assessment
    {
        // Look for existing assessment by name in this offering's groups
        $existing = Assessment::whereHas('assessmentGroup', fn ($q) => $q->where('course_offering_id', $offering->id))
            ->where('name', $name)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Find or create the "Labs" CA group
        $group = AssessmentGroup::firstOrCreate(
            [
                'course_offering_id' => $offering->id,
                'name' => 'Labs',
                'type' => 'ca',
            ],
            [
                'weight_percentage' => 0,
                'weight_mode' => 'percentage',
                'sort_order' => 1,
            ]
        );

        // Create new assessment
        $sortOrder = $group->assessments()->count() + 1;

        return Assessment::create([
            'name' => $name,
            'course_id' => $offering->course_id,
            'assessment_group_id' => $group->id,
            'weight' => 1,
            'max_raw_score' => 100,
            'normalized_to' => 10,
            'has_subsections' => true,
            'is_published' => false,
            'sort_order' => $sortOrder,
        ]);
    }
}
