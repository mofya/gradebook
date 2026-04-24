<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MissedAssessmentAppeal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MissedAppealEvidenceController extends Controller
{
    public function __invoke(Request $request, MissedAssessmentAppeal $appeal): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->isAdmin() || $user->isLecturer()), 403);

        abort_if(! $appeal->evidence_path, 404);
        abort_unless(Storage::exists($appeal->evidence_path), 404);

        $filename = basename($appeal->evidence_path);

        return Storage::download($appeal->evidence_path, $filename);
    }
}
