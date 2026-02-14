<?php

namespace App\Services;

use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class TranscriptService
{
    public function __construct(
        protected GradingService $gradingService
    ) {}

    /**
     * Generate transcript data for a student.
     *
     * @return array<string, mixed>
     */
    public function generateTranscriptData(Student $student): array
    {
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

        return [
            'student' => $student,
            'courses' => $courseResults,
            'cumulative_gpa' => $this->gradingService->calculateCumulativeGpa($gpaInput),
            'generated_at' => now()->format('F j, Y'),
        ];
    }

    /**
     * Generate and return a PDF transcript response.
     */
    public function downloadPdf(Student $student): Response
    {
        $data = $this->generateTranscriptData($student);

        $pdf = Pdf::loadView('transcripts.pdf', $data);

        $filename = 'transcript_'.$student->id.'_'.now()->format('Ymd').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Stream a PDF transcript.
     */
    public function streamPdf(Student $student): Response
    {
        $data = $this->generateTranscriptData($student);

        $pdf = Pdf::loadView('transcripts.pdf', $data);

        $filename = 'transcript_'.$student->id.'_'.now()->format('Ymd').'.pdf';

        return $pdf->stream($filename);
    }
}
