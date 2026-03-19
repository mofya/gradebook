<?php

namespace App\Filament\Student\Pages;

use App\Models\Enrollment;
use App\Models\Student;
use App\Services\GradingService;
use App\Services\TranscriptService;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MyTranscript extends Page
{
    protected static bool $shouldRegisterNavigation = true;

    protected string $view = 'filament.student.pages.my-transcript';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $title = 'My Transcript';

    protected static ?int $navigationSort = 2;

    public function getViewData(): array
    {
        $user = auth()->user();

        $student = Student::query()
            ->where('email', $user->email)
            ->first();

        if (! $student) {
            return ['student' => null, 'transcriptData' => null, 'semesters' => collect()];
        }

        $transcriptService = app(TranscriptService::class);
        $transcriptData = $transcriptService->generateTranscriptData($student);

        // Build semester-by-semester view from enrollments
        $gradingService = app(GradingService::class);
        $enrollments = Enrollment::query()
            ->where('student_id', $student->id)
            ->whereHas('courseOffering', fn ($q) => $q->where('is_published', true))
            ->with(['courseOffering.course', 'courseOffering.semester.year'])
            ->whereNotNull('final_total')
            ->get();

        $semesters = $enrollments
            ->groupBy(fn ($e) => $e->courseOffering->semester_id)
            ->map(function ($group) use ($gradingService) {
                $semester = $group->first()->courseOffering->semester;
                $semesterResults = $group->map(fn ($e) => [
                    'mark' => (float) $e->final_total,
                    'credits' => $e->courseOffering->course->credits,
                ])->values()->toArray();

                return [
                    'name' => ($semester->year->name ?? '').' '.$semester->name,
                    'gpa' => $gradingService->calculateSemesterGpa($semesterResults),
                    'enrollments' => $group,
                ];
            })
            ->values();

        return [
            'student' => $student,
            'transcriptData' => $transcriptData,
            'semesters' => $semesters,
        ];
    }

    public function downloadTranscript(): StreamedResponse
    {
        $user = auth()->user();
        $student = Student::query()->where('email', $user->email)->firstOrFail();

        $service = app(TranscriptService::class);
        $data = $service->generateTranscriptData($student);
        $filename = 'transcript_'.$student->student_id_number.'_'.now()->format('Ymd').'.pdf';

        return response()->streamDownload(function () use ($data) {
            echo Pdf::loadView('transcripts.pdf', $data)->output();
        }, $filename);
    }
}
