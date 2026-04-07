<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\TranscriptController;
use App\Http\Controllers\Api\V1\OfferingController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Courses — any authenticated user can view
    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/{course}', [CourseController::class, 'show']);

    // Grades — admin/lecturer can view any student; students can only view own
    Route::get('/grades/{student}', [GradeController::class, 'index']);

    // Grade submission — lecturers and admins only
    Route::post('/grades/store', [GradeController::class, 'store'])
        ->middleware('role:admin,lecturer');

    // Transcripts — admin/lecturer can view any; students can only view own
    Route::get('/transcripts/{student}', [TranscriptController::class, 'show']);
    Route::get('/transcripts/{student}/download', [TranscriptController::class, 'download']);

    Route::prefix('v1')->middleware('role:admin,lecturer')->group(function () {
        Route::get('/offerings', [OfferingController::class, 'index']);
        Route::post('/offerings', [OfferingController::class, 'create']);
        Route::get('/offerings/{offering}', [OfferingController::class, 'show']);
        Route::get('/offerings/{offering}/enrollments', [OfferingController::class, 'enrollments']);
        Route::get('/offerings/{offering}/grades', [OfferingController::class, 'grades']);
        Route::post('/offerings/{offering}/lab-grades', [OfferingController::class, 'importLabGrades']);
        Route::get('/offerings/{offering}/students/{identifier}/grades', [OfferingController::class, 'studentGrades']);
        Route::get('/offerings/{offering}/assessments', [OfferingController::class, 'assessments']);
        Route::get('/offerings/{offering}/unmatched', [OfferingController::class, 'unmatched']);
        Route::post('/offerings/{offering}/enrollments', [OfferingController::class, 'bulkEnroll']);
        Route::patch('/offerings/{offering}/status', [OfferingController::class, 'updateStatus']);
        Route::get('/offerings/{offering}/verification-link', [OfferingController::class, 'getVerificationLink']);
        Route::post('/offerings/{offering}/verification-link', [OfferingController::class, 'verificationLink']);
        Route::delete('/offerings/{offering}/lab-grades/{assessment}', [OfferingController::class, 'deleteLabGrades']);
        Route::get('/offerings/{offering}/grade-summary', [OfferingController::class, 'gradeSummary']);
        Route::get('/offerings/{offering}/export', [OfferingController::class, 'export']);
        Route::get('/offerings/{offering}/changelog', [OfferingController::class, 'changelog']);
        Route::post('/offerings/{offering}/enrollments/sync', [OfferingController::class, 'syncEnrollments']);
        Route::get('/offerings/{offering}/students/{identifier}', [OfferingController::class, 'studentProfile']);
        Route::patch('/offerings/{offering}/enrollments/{identifier}', [OfferingController::class, 'updateEnrollment']);
    });
});
