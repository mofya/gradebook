<?php

use App\Http\Controllers\Web\TranscriptController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/transcripts/{student}/download', [TranscriptController::class, 'download'])
        ->name('transcripts.download');
});
