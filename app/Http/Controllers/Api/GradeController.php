<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GradeResource;
use App\Models\Grade;
use App\Models\Student;
use App\Services\GradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GradeController extends Controller
{
    public function __construct(
        protected GradingService $gradingService
    ) {}

    /**
     * Get grades for a specific student.
     */
    public function index(Request $request, Student $student): AnonymousResourceCollection
    {
        $user = $request->user();

        // Students can only view their own grades (matched by email)
        if ($user->isStudent()) {
            $ownStudent = Student::query()->where('email', $user->email)->first();
            if (! $ownStudent || $ownStudent->id !== $student->id) {
                abort(403, 'You can only view your own grades.');
            }
        }

        $grades = Grade::query()
            ->where('student_id', $student->id)
            ->with(['course', 'assessment', 'lecturer'])
            ->get();

        return GradeResource::collection($grades);
    }

    /**
     * Store/submit marks for a student.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'course_id' => ['required', 'exists:courses,id'],
            'assessment_id' => ['required', 'exists:assessments,id'],
            'grade' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        if (! $this->gradingService->isValidMark($validated['grade'])) {
            return response()->json(['message' => 'Mark must be between 0 and 100.'], 422);
        }

        $grade = Grade::query()->updateOrCreate(
            [
                'student_id' => $validated['student_id'],
                'course_id' => $validated['course_id'],
                'assessment_id' => $validated['assessment_id'],
            ],
            [
                'grade' => $validated['grade'],
                'grade_letter' => $this->gradingService->getLetterGrade($validated['grade']),
                'lecturer_id' => $request->user()->id,
            ]
        );

        $grade->load(['course', 'assessment']);

        return response()->json([
            'message' => 'Grade saved successfully.',
            'data' => new GradeResource($grade),
        ], 201);
    }
}
