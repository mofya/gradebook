<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\TranscriptService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TranscriptController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected TranscriptService $transcriptService
    ) {}

    /**
     * Get transcript data for a student (JSON).
     */
    public function show(Student $student): JsonResponse
    {
        $this->authorize('viewTranscript', $student);

        $data = $this->transcriptService->generateTranscriptData($student);

        return response()->json([
            'student' => [
                'id' => $data['student']->id,
                'name' => $data['student']->first_name.' '.$data['student']->last_name,
                'email' => $data['student']->email,
            ],
            'courses' => $data['courses'],
            'cumulative_gpa' => $data['cumulative_gpa'],
        ]);
    }

    /**
     * Download a PDF transcript for a student.
     */
    public function download(Student $student): Response
    {
        $this->authorize('viewTranscript', $student);

        return $this->transcriptService->downloadPdf($student);
    }
}
