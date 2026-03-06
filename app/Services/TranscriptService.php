<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class TranscriptService
{
    public function __construct(
        protected GradingService $gradingService
    ) {}

    /**
     * Generate transcript data for a student using the enrollment-based data model.
     *
     * @return array<string, mixed>
     */
    public function generateTranscriptData(Student $student): array
    {
        $enrollments = Enrollment::query()
            ->where('student_id', $student->id)
            ->whereHas('courseOffering', fn ($q) => $q->where('is_published', true))
            ->whereNotNull('final_total')
            ->with(['courseOffering.course', 'courseOffering.semester.year'])
            ->get();

        $courseResults = [];
        $gpaInput = [];

        foreach ($enrollments as $enrollment) {
            $course = $enrollment->courseOffering->course;
            $courseResults[] = [
                'course_code' => $course->code,
                'course_name' => $course->name,
                'credits' => $course->credits,
                'mark' => (float) $enrollment->final_total,
                'letter_grade' => $enrollment->final_grade,
                'grade_points' => $enrollment->grade_points !== null ? (float) $enrollment->grade_points : null,
            ];

            if ($enrollment->final_grade !== null && ! in_array($enrollment->final_grade, ['NE', 'DV', 'EX', 'ABS', 'WH'], true)) {
                $gpaInput[] = [
                    'mark' => (float) $enrollment->final_total,
                    'credits' => $course->credits,
                ];
            }
        }

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

        $filename = 'transcript_'.$student->student_id_number.'_'.now()->format('Ymd').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Stream a PDF transcript.
     */
    public function streamPdf(Student $student): Response
    {
        $data = $this->generateTranscriptData($student);

        $pdf = Pdf::loadView('transcripts.pdf', $data);

        $filename = 'transcript_'.$student->student_id_number.'_'.now()->format('Ymd').'.pdf';

        return $pdf->stream($filename);
    }
}
