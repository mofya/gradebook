<?php

use App\Models\Student;
use App\Services\TranscriptService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/transcripts/{student}/download', function (Student $student) {
    $user = auth()->user();

    // Only admin/lecturer can download any transcript; students only their own
    if ($user->isStudent()) {
        $ownStudent = Student::query()->where('email', $user->email)->first();
        if (! $ownStudent || $ownStudent->id !== $student->id) {
            abort(403, 'You can only download your own transcript.');
        }
    }

    return app(TranscriptService::class)->downloadPdf($student);
})->middleware('auth')->name('transcripts.download');
