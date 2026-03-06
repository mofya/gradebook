<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\GradingService;
use App\Services\TranscriptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TranscriptController extends Controller
{
    public function __construct(
        protected GradingService $gradingService
    ) {}

    /**
     * Get transcript data for a student (JSON for now, PDF in Phase 4).
     */
    public function show(Request $request, Student $student): JsonResponse
    {
        $this->authorizeStudentAccess($request, $student);

        $student->load(['courses.department', 'grades.assessment', 'grades.course']);

        $courseResults = [];

        foreach ($student->courses as $course) {
            $totalMark = $student->totalGradeForCourse($course->id);
            $courseResults[] = [
                'course_code' => $course->code,
                'course_name' => $course->name,
                'credits' => $course->credits,
                'mark' => $totalMark !== null ? round($totalMark, 2) : null,
                'letter_grade' => $totalMark !== null ? $this->gradingService->getLetterGrade($totalMark) : null,
                'grade_points' => $totalMark !== null ? $this->gradingService->getGradePoints($totalMark) : null,
            ];
        }

        $gradedResults = array_filter($courseResults, fn ($r) => $r['mark'] !== null);
        $gpaInput = array_map(fn ($r) => ['mark' => $r['mark'], 'credits' => $r['credits']], $gradedResults);
        $cgpa = $this->gradingService->calculateCumulativeGpa($gpaInput);

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => $student->first_name.' '.$student->last_name,
                'email' => $student->email,
            ],
            'courses' => $courseResults,
            'cumulative_gpa' => $cgpa,
        ]);
    }

    /**
     * Download a PDF transcript for a student.
     */
    public function download(Request $request, Student $student): Response
    {
        $this->authorizeStudentAccess($request, $student);

        return app(TranscriptService::class)->downloadPdf($student);
    }

    /**
     * Verify the requesting user has access to this student's data.
     */
    protected function authorizeStudentAccess(Request $request, Student $student): void
    {
        $user = $request->user();

        if ($user->isStudent()) {
            $ownStudent = Student::query()->where('email', $user->email)->first();
            if (! $ownStudent || $ownStudent->id !== $student->id) {
                abort(403, 'You can only access your own transcript.');
            }
        }
    }
}
