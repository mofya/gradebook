<?php

use App\Http\Controllers\Web\TranscriptController;
use App\Livewire\PublicClassGrades;
use App\Livewire\PublicGrades;
use App\Livewire\StudentVerification;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/verify/{token}', StudentVerification::class)
    ->name('student.verify')
    ->middleware('throttle:30,1');

Route::get('/grades/{token}', PublicGrades::class)
    ->name('student.grades')
    ->middleware('throttle:30,1');

Route::get('/class-grades/{token}', PublicClassGrades::class)
    ->name('class.grades')
    ->middleware('throttle:30,1');

Route::middleware('auth')->group(function () {
    Route::get('/transcripts/{student}/download', [TranscriptController::class, 'download'])
        ->name('transcripts.download');
});
