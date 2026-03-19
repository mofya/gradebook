<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\TranscriptService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Response;

class TranscriptController extends Controller
{
    use AuthorizesRequests;

    public function download(Student $student): Response
    {
        $this->authorize('viewTranscript', $student);

        return app(TranscriptService::class)->downloadPdf($student);
    }
}
