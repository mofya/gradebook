<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGradeRequest;
use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\GradeResult;
use App\Models\Student;
use App\Services\GradingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class GradeController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected GradingService $gradingService
    ) {}

    /**
     * Get grades for a specific student.
     */
    public function index(Student $student): JsonResponse
    {
        $this->authorize('viewGrades', $student);

        $enrollments = Enrollment::query()
            ->where('student_id', $student->id)
            ->with(['courseOffering.course', 'gradeResults.assessment'])
            ->get();

        $results = $enrollments->map(fn (Enrollment $enrollment) => [
            'enrollment_id' => $enrollment->id,
            'course' => [
                'id' => $enrollment->courseOffering->course->id,
                'code' => $enrollment->courseOffering->course->code,
                'name' => $enrollment->courseOffering->course->name,
            ],
            'ca_total' => $enrollment->ca_total,
            'exam_score' => $enrollment->exam_score,
            'final_total' => $enrollment->final_total,
            'final_grade' => $enrollment->final_grade,
            'grade_points' => $enrollment->grade_points,
            'grade_results' => $enrollment->gradeResults->map(fn (GradeResult $gr) => [
                'assessment' => $gr->assessment?->name,
                'raw_score' => $gr->raw_score,
                'normalized_score' => $gr->normalized_score,
            ]),
        ]);

        return response()->json(['data' => $results]);
    }

    /**
     * Store/submit a score for a student on an assessment.
     */
    public function store(StoreGradeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $assessment = Assessment::with('assessmentGroup.courseOffering')->findOrFail($validated['assessment_id']);
        $courseOffering = $assessment->assessmentGroup->courseOffering;

        // Authorization: verify the lecturer is assigned to this course offering
        if (! $request->user()->isAdmin() && $courseOffering->lecturer_id !== $request->user()->id) {
            return response()->json(['message' => 'You are not assigned to this course offering.'], 403);
        }

        $enrollment = Enrollment::query()
            ->where('student_id', $validated['student_id'])
            ->where('course_offering_id', $courseOffering->id)
            ->firstOrFail();

        if (! $this->gradingService->isValidMark($validated['grade'])) {
            return response()->json(['message' => 'Mark must be between 0 and 100.'], 422);
        }

        $gradeResult = GradeResult::updateOrCreate(
            [
                'enrollment_id' => $enrollment->id,
                'assessment_id' => $assessment->id,
            ],
            [
                'raw_score' => $validated['grade'],
                'graded_by' => $request->user()->id,
                'source' => 'api',
            ]
        );

        $normalized = $gradeResult->calculateNormalizedScore();
        if ($normalized !== null) {
            $gradeResult->update(['normalized_score' => $normalized]);
        }

        $this->gradingService->resolveGrade($enrollment);

        $gradeResult->load('assessment');

        return response()->json([
            'message' => 'Grade saved successfully.',
            'data' => [
                'id' => $gradeResult->id,
                'assessment' => [
                    'id' => $gradeResult->assessment->id,
                    'name' => $gradeResult->assessment->name,
                    'weight' => $gradeResult->assessment->weight,
                ],
                'raw_score' => $gradeResult->raw_score,
                'normalized_score' => $gradeResult->normalized_score,
            ],
        ], 201);
    }
}
