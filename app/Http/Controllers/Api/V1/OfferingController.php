<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\GradeSheetExport;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeAuditLog;
use App\Models\GradeResult;
use App\Models\Student;
use App\Models\SubsectionScore;
use App\Models\UnmatchedLabGrade;
use App\Models\UsernameDispute;
use App\Services\BackfillLabGradesService;
use App\Services\GradingService;
use App\Services\LabGradeImportService;
use App\Services\ReportingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OfferingController extends Controller
{
    use AuthorizesRequests;

    /**
     * List course offerings with basic info.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', CourseOffering::class);

        $query = CourseOffering::query()
            ->with(['course', 'semester.year', 'lecturer']);

        if (auth()->user()->isLecturer()) {
            $query->where('lecturer_id', auth()->id());
        }

        $offerings = $query->paginate(20);

        return response()->json([
            'data' => $offerings->map(fn ($o) => [
                'id' => $o->id,
                'course_code' => $o->course->code,
                'course_name' => $o->course->name,
                'semester' => ($o->semester->year->name ?? '').' '.$o->semester->name,
                'lecturer' => $o->lecturer?->name,
                'status' => $o->status,
                'ca_weight' => $o->ca_weight,
                'exam_weight' => $o->exam_weight,
            ]),
            'meta' => [
                'current_page' => $offerings->currentPage(),
                'last_page' => $offerings->lastPage(),
                'total' => $offerings->total(),
            ],
        ]);
    }

    /**
     * Create a new course offering.
     */
    public function create(Request $request): JsonResponse
    {
        $this->authorize('create', CourseOffering::class);

        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'semester_id' => 'required|exists:semesters,id',
            'lecturer_id' => 'nullable|exists:users,id',
            'grading_scheme_id' => 'nullable|exists:grading_schemes,id',
            'section' => 'nullable|string|max:50',
            'ca_weight' => 'required|numeric|min:0|max:100',
            'exam_weight' => 'required|numeric|min:0|max:100',
        ]);

        if (bcadd((string) $validated['ca_weight'], (string) $validated['exam_weight'], 2) !== '100.00') {
            return response()->json(['error' => 'CA weight and exam weight must sum to 100.'], 422);
        }

        $offering = CourseOffering::create([
            ...$validated,
            'status' => 'draft',
            'is_published' => false,
        ]);

        $offering->load(['course', 'semester.year', 'lecturer']);

        return response()->json([
            'data' => [
                'id' => $offering->id,
                'course_code' => $offering->course->code,
                'course_name' => $offering->course->name,
                'semester' => ($offering->semester->year->name ?? '').' '.$offering->semester->name,
                'lecturer' => $offering->lecturer?->name,
                'status' => $offering->status,
                'ca_weight' => $offering->ca_weight,
                'exam_weight' => $offering->exam_weight,
            ],
        ], 201);
    }

    /**
     * Show a single course offering with enrollments and assessments.
     */
    public function show(CourseOffering $offering): JsonResponse
    {
        $this->authorize('view', $offering);

        $offering->load([
            'course',
            'semester.year',
            'lecturer',
            'enrollments.student',
            'assessmentGroups.assessments',
        ]);

        return response()->json([
            'data' => [
                'id' => $offering->id,
                'course_code' => $offering->course->code,
                'course_name' => $offering->course->name,
                'semester' => ($offering->semester->year->name ?? '').' '.$offering->semester->name,
                'lecturer' => $offering->lecturer?->name,
                'status' => $offering->status,
                'ca_weight' => $offering->ca_weight,
                'exam_weight' => $offering->exam_weight,
                'enrollments' => $offering->enrollments->map(fn ($e) => [
                    'id' => $e->id,
                    'student_name' => $e->student->first_name.' '.$e->student->last_name,
                    'student_id' => $e->student->student_id_number,
                    'email' => $e->student->email,
                    'github_username' => $e->student->github_username,
                    'status' => $e->status,
                    'final_total' => $e->final_total,
                    'final_grade' => $e->final_grade,
                    'grade_points' => $e->grade_points,
                ]),
                'assessment_groups' => $offering->assessmentGroups->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'type' => $g->type,
                    'weight_percentage' => $g->weight_percentage,
                    'assessments' => $g->assessments->map(fn ($a) => [
                        'id' => $a->id,
                        'name' => $a->name,
                        'max_raw_score' => $a->max_raw_score,
                    ]),
                ]),
            ],
        ]);
    }

    /**
     * Get enrollments for an offering.
     */
    public function enrollments(CourseOffering $offering): JsonResponse
    {
        $this->authorize('view', $offering);

        $offering->load('enrollments.student');

        return response()->json([
            'data' => $offering->enrollments->map(fn ($e) => [
                'id' => $e->id,
                'student_name' => $e->student->first_name.' '.$e->student->last_name,
                'student_id' => $e->student->student_id_number,
                'email' => $e->student->email,
                'github_username' => $e->student->github_username,
                'status' => $e->status,
                'exam_status' => $e->exam_status,
                'ca_total' => $e->ca_total,
                'exam_score' => $e->exam_score,
                'final_total' => $e->final_total,
                'final_grade' => $e->final_grade,
                'grade_points' => $e->grade_points,
                'remarks' => $e->remarks,
            ]),
        ]);
    }

    /**
     * Get grade results for an offering (enhanced with subsections).
     */
    public function grades(CourseOffering $offering): JsonResponse
    {
        $this->authorize('view', $offering);

        $offering->load([
            'enrollments.gradeResults.assessment',
            'enrollments.gradeResults.subsectionScores.assessmentSubsection',
            'enrollments.student',
        ]);

        $results = $offering->enrollments->flatMap(function ($enrollment) {
            return $enrollment->gradeResults->map(fn ($gr) => [
                'student_id' => $enrollment->student->student_id_number,
                'student_name' => $enrollment->student->first_name.' '.$enrollment->student->last_name,
                'github_username' => $enrollment->student->github_username,
                'assessment' => $gr->assessment->name,
                'raw_score' => $gr->raw_score,
                'normalized_score' => $gr->normalized_score,
                'is_excused' => $gr->is_excused,
                'subsections' => $gr->subsectionScores->map(fn ($ss) => [
                    'name' => $ss->assessmentSubsection->name,
                    'score' => $ss->score,
                ]),
                'student_feedback' => $gr->student_feedback,
                'graded_at' => $gr->updated_at?->toIso8601String(),
            ]);
        });

        return response()->json(['data' => $results->values()]);
    }

    /**
     * Bulk import lab grades via JSON.
     */
    public function importLabGrades(Request $request, CourseOffering $offering): JsonResponse
    {
        $this->authorize('update', $offering);

        $validated = $request->validate([
            'assessment_name' => 'required|string',
            'grades' => 'required|array|min:1',
            'grades.*.github_username' => 'nullable|required_without:grades.*.student_id|string',
            'grades.*.student_id' => 'nullable|required_without:grades.*.github_username|string',
            'grades.*.final_score' => 'required|numeric|min:0|max:100',
            'grades.*.visible_tests' => 'nullable|numeric|min:0|max:100',
            'grades.*.hidden_tests' => 'nullable|numeric|min:0|max:100',
            'grades.*.code_quality' => 'nullable|numeric|min:0|max:100',
            'grades.*.plagiarism_flag' => 'nullable|string',
            'grades.*.student_feedback' => 'nullable|string',
            'grades.*.instructor_notes' => 'nullable|string',
        ]);

        // Find or create the assessment
        $assessment = $this->resolveAssessment($offering, $validated['assessment_name']);

        // Convert JSON grades to the CSV row format that LabGradeImportService expects
        $rows = collect($validated['grades'])->map(fn ($grade) => [
            'GitHub Username' => $grade['github_username'] ?? '',
            'Student ID' => $grade['student_id'] ?? '',
            'Final Score (%)' => (string) $grade['final_score'],
            'Visible Tests (%)' => (string) ($grade['visible_tests'] ?? 0),
            'Hidden Tests (%)' => (string) ($grade['hidden_tests'] ?? 0),
            'Code Quality (%)' => (string) ($grade['code_quality'] ?? 0),
            'Student Feedback' => $grade['student_feedback'] ?? '',
            'Plagiarism Note' => ($grade['plagiarism_flag'] ?? '') === 'Yes'
                ? 'Plagiarism flagged.'
                : '',
            'Instructor Notes' => $grade['instructor_notes'] ?? '',
        ])->all();

        $service = app(LabGradeImportService::class);
        $stats = $service->import($offering, $assessment, $rows);

        return response()->json([
            'message' => 'Lab grades imported successfully.',
            'data' => $stats,
        ]);
    }

    /**
     * Get a specific student's grades for an offering by external identifier.
     */
    public function studentGrades(CourseOffering $offering, string $identifier): JsonResponse
    {
        $this->authorize('view', $offering);

        $offering->load(['course', 'semester.year']);

        // Try student_id_number first, then github_username
        $student = Student::where('student_id_number', $identifier)->first()
            ?? Student::whereRaw('LOWER(github_username) = ?', [strtolower($identifier)])->first();

        if (! $student) {
            return response()->json(['error' => "Student not found: {$identifier}"], 404);
        }

        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('course_offering_id', $offering->id)
            ->first();

        if (! $enrollment) {
            return response()->json([
                'error' => "Student {$identifier} is not enrolled in this offering.",
            ], 404);
        }

        $enrollment->load([
            'gradeResults.assessment',
            'gradeResults.subsectionScores.assessmentSubsection',
        ]);

        return response()->json([
            'data' => [
                'student' => [
                    'student_id_number' => $student->student_id_number,
                    'name' => $student->first_name.' '.$student->last_name,
                    'email' => $student->email,
                    'github_username' => $student->github_username,
                ],
                'offering' => [
                    'course_code' => $offering->course->code,
                    'semester' => ($offering->semester->year->name ?? '').' '.$offering->semester->name,
                ],
                'ca_total' => $enrollment->ca_total,
                'exam_score' => $enrollment->exam_score,
                'final_total' => $enrollment->final_total,
                'final_grade' => $enrollment->final_grade,
                'assessments' => $enrollment->gradeResults->map(fn ($gr) => [
                    'name' => $gr->assessment->name,
                    'raw_score' => $gr->raw_score,
                    'normalized_score' => $gr->normalized_score,
                    'subsections' => $gr->subsectionScores->map(fn ($ss) => [
                        'name' => $ss->assessmentSubsection->name,
                        'score' => $ss->score,
                    ]),
                    'student_feedback' => $gr->student_feedback,
                    'graded_at' => $gr->updated_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    /**
     * List assessment groups and assessments for an offering.
     */
    public function assessments(CourseOffering $offering): JsonResponse
    {
        $this->authorize('view', $offering);

        $offering->load('assessmentGroups.assessments');

        return response()->json([
            'data' => $offering->assessmentGroups->sortBy('sort_order')->values()->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'type' => $g->type,
                'weight_percentage' => $g->weight_percentage,
                'assessments' => $g->assessments->sortBy('sort_order')->values()->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'max_raw_score' => $a->max_raw_score,
                    'normalized_to' => $a->normalized_to,
                    'has_subsections' => $a->has_subsections,
                    'sort_order' => $a->sort_order,
                ]),
            ]),
        ]);
    }

    /**
     * List unmatched lab grades for an offering.
     */
    public function unmatched(CourseOffering $offering): JsonResponse
    {
        $this->authorize('view', $offering);

        $items = UnmatchedLabGrade::where('course_offering_id', $offering->id)
            ->where('status', 'unmatched')
            ->with('assessment')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $items->map(fn ($item) => [
                'id' => $item->id,
                'github_username' => $item->github_username,
                'assessment' => $item->assessment->name,
                'status' => $item->status,
                'row_data' => $item->row_data,
                'created_at' => $item->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Bulk enroll students by student ID numbers.
     */
    public function bulkEnroll(Request $request, CourseOffering $offering): JsonResponse
    {
        $this->authorize('update', $offering);

        $validated = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'required|string',
            'source' => 'nullable|string',
        ]);

        $source = $validated['source'] ?? 'api';
        $enrolled = 0;
        $alreadyEnrolled = 0;
        $notFound = [];

        foreach ($validated['student_ids'] as $studentIdNumber) {
            $student = Student::where('student_id_number', $studentIdNumber)->first();

            if (! $student) {
                $notFound[] = $studentIdNumber;

                continue;
            }

            $exists = Enrollment::where('student_id', $student->id)
                ->where('course_offering_id', $offering->id)
                ->exists();

            if ($exists) {
                $alreadyEnrolled++;

                continue;
            }

            Enrollment::create([
                'student_id' => $student->id,
                'course_offering_id' => $offering->id,
                'source' => $source,
                'status' => 'enrolled',
            ]);

            $enrolled++;
        }

        return response()->json([
            'data' => [
                'enrolled' => $enrolled,
                'already_enrolled' => $alreadyEnrolled,
                'not_found' => $notFound,
            ],
        ]);
    }

    /**
     * Transition the offering status (activate/lock/publish).
     */
    public function updateStatus(Request $request, CourseOffering $offering): JsonResponse
    {
        $this->authorize('update', $offering);

        $validated = $request->validate([
            'action' => 'required|string|in:activate,lock,publish',
        ]);

        try {
            match ($validated['action']) {
                'activate' => $offering->activate(),
                'lock' => $offering->lock(),
                'publish' => $offering->publish(),
            };
        } catch (\LogicException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'id' => $offering->id,
                'status' => $offering->fresh()->status,
                'message' => 'Offering '.$validated['action'].'d.',
            ],
        ]);
    }

    /**
     * View the current verification link status for an offering.
     */
    public function getVerificationLink(CourseOffering $offering): JsonResponse
    {
        $this->authorize('view', $offering);

        if (! $offering->verification_token) {
            return response()->json([
                'data' => [
                    'active' => false,
                    'message' => 'No verification link has been generated.',
                ],
            ]);
        }

        $isValid = $offering->hasValidVerificationToken();

        return response()->json([
            'data' => [
                'active' => $isValid,
                'verify_url' => route('student.verify', ['token' => $offering->verification_token]),
                'grades_url' => route('student.grades', ['token' => $offering->verification_token]),
                'expires_at' => $offering->verification_expires_at->toIso8601String(),
                'expired' => ! $isValid,
                'time_remaining' => $isValid ? $offering->verification_expires_at->diffForHumans() : null,
            ],
        ]);
    }

    /**
     * Generate, extend, or revoke a verification link for the offering.
     */
    public function verificationLink(Request $request, CourseOffering $offering): JsonResponse
    {
        $this->authorize('update', $offering);

        $validated = $request->validate([
            'action' => 'required|string|in:generate,extend,revoke',
            'expiry_days' => 'required_if:action,generate|required_if:action,extend|nullable|integer|min:1|max:30',
        ]);

        if ($validated['action'] === 'revoke') {
            $offering->revokeVerificationToken();

            return response()->json([
                'data' => ['message' => 'Verification link revoked.'],
            ]);
        }

        if ($validated['action'] === 'extend') {
            if (! $offering->verification_token) {
                return response()->json(['error' => 'No verification token exists to extend.'], 422);
            }

            $offering->extendVerificationToken((int) $validated['expiry_days']);

            return response()->json([
                'data' => [
                    'verify_url' => route('student.verify', ['token' => $offering->verification_token]),
                    'grades_url' => route('student.grades', ['token' => $offering->verification_token]),
                    'expires_at' => $offering->verification_expires_at->toIso8601String(),
                    'message' => 'Verification link extended.',
                ],
            ]);
        }

        $offering->generateVerificationToken((int) $validated['expiry_days']);

        return response()->json([
            'data' => [
                'verify_url' => route('student.verify', ['token' => $offering->verification_token]),
                'grades_url' => route('student.grades', ['token' => $offering->verification_token]),
                'expires_at' => $offering->verification_expires_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Delete all grade results for a specific assessment in an offering.
     */
    public function deleteLabGrades(CourseOffering $offering, Assessment $assessment): JsonResponse
    {
        $this->authorize('update', $offering);

        // Verify the assessment belongs to this offering
        $belongsToOffering = $assessment->assessmentGroup
            && $assessment->assessmentGroup->course_offering_id === $offering->id;

        if (! $belongsToOffering) {
            return response()->json(['error' => 'Assessment does not belong to this offering.'], 404);
        }

        $enrollmentIds = $offering->enrollments()->pluck('id');

        // Delete subsection scores first
        $gradeResultIds = GradeResult::whereIn('enrollment_id', $enrollmentIds)
            ->where('assessment_id', $assessment->id)
            ->pluck('id');

        SubsectionScore::whereIn('grade_result_id', $gradeResultIds)->delete();

        $deletedCount = GradeResult::whereIn('enrollment_id', $enrollmentIds)
            ->where('assessment_id', $assessment->id)
            ->delete();

        // Recalculate all grades for the offering
        app(GradingService::class)->resolveAllGrades($offering);

        return response()->json([
            'data' => [
                'deleted' => $deletedCount,
                'message' => "{$deletedCount} grade result(s) deleted for {$assessment->name}.",
            ],
        ]);
    }

    /**
     * Update a student's details (GitHub username, personal email) within an offering.
     */
    public function updateEnrollment(Request $request, CourseOffering $offering, string $identifier): JsonResponse
    {
        $this->authorize('update', $offering);

        $validated = $request->validate([
            'github_username' => 'nullable|string|max:39',
            'personal_email' => 'nullable|email|max:255',
        ]);

        $student = Student::where('student_id_number', $identifier)->first()
            ?? Student::whereRaw('LOWER(github_username) = ?', [strtolower($identifier)])->first();

        if (! $student) {
            return response()->json(['error' => "Student not found: {$identifier}"], 404);
        }

        $enrolled = Enrollment::where('student_id', $student->id)
            ->where('course_offering_id', $offering->id)
            ->exists();

        if (! $enrolled) {
            return response()->json(['error' => "Student {$identifier} is not enrolled in this offering."], 404);
        }

        $username = isset($validated['github_username']) ? trim($validated['github_username']) : null;

        if ($username !== null && $username !== '') {
            $taken = Student::where('github_username', $username)
                ->where('id', '!=', $student->id)
                ->exists();

            if ($taken) {
                return response()->json(['error' => 'This GitHub username is already linked to another student.'], 422);
            }
        }

        $oldValues = [
            'github_username' => $student->github_username,
            'personal_email' => $student->personal_email,
        ];

        $updates = [];
        if (array_key_exists('github_username', $validated)) {
            $updates['github_username'] = $username ?: null;
        }
        if (array_key_exists('personal_email', $validated)) {
            $updates['personal_email'] = trim($validated['personal_email']) ?: null;
        }

        $student->update($updates);

        GradeAuditLog::create([
            'auditable_type' => Student::class,
            'auditable_id' => $student->id,
            'user_id' => auth()->id(),
            'action' => 'api_enrollment_update',
            'old_values' => $oldValues,
            'new_values' => $updates,
            'ip_address' => $request->ip(),
        ]);

        $backfillCount = 0;
        if (isset($updates['github_username']) && $updates['github_username'] && $updates['github_username'] !== $oldValues['github_username']) {
            $backfill = app(BackfillLabGradesService::class)->backfillForStudent($student);
            $backfillCount = $backfill['grades_created'] ?? 0;
        }

        return response()->json([
            'data' => [
                'student_id_number' => $student->student_id_number,
                'github_username' => $student->github_username,
                'personal_email' => $student->personal_email,
                'grades_backfilled' => $backfillCount,
            ],
        ]);
    }

    /**
     * List missed-assessment appeals for an offering.
     */
    public function appeals(CourseOffering $offering): JsonResponse
    {
        $this->authorize('view', $offering);

        $appeals = $offering->missedAssessmentAppeals()
            ->with(['student', 'items.assessment'])
            ->orderByDesc('submitted_at')
            ->get();

        return response()->json([
            'data' => $appeals->map(fn ($appeal) => [
                'id' => $appeal->id,
                'student_id_number' => $appeal->student->student_id_number,
                'student_name' => trim(($appeal->student->first_name ?? '').' '.($appeal->student->last_name ?? '')),
                'student_email' => $appeal->student->email,
                'narrative' => $appeal->narrative,
                'other_notes' => $appeal->other_notes,
                'dean_confirmed' => (bool) $appeal->dean_confirmed,
                'has_evidence' => (bool) $appeal->evidence_path,
                'status' => $appeal->status,
                'submitted_at' => $appeal->submitted_at?->toIso8601String(),
                'reviewed_at' => $appeal->reviewed_at?->toIso8601String(),
                'items' => $appeal->items->map(fn ($item) => [
                    'assessment_id' => $item->assessment_id,
                    'assessment_name' => $item->assessment->name ?? null,
                    'status' => $item->status,
                    'reviewer_notes' => $item->reviewer_notes,
                ]),
            ])->values(),
        ]);
    }

    /**
     * Return aggregated grade statistics for an offering.
     */
    public function gradeSummary(CourseOffering $offering): JsonResponse
    {
        $this->authorize('view', $offering);

        $report = app(ReportingService::class)->generateOfferingReport($offering);

        return response()->json([
            'data' => [
                'stats' => $report['stats'],
                'distribution' => $report['distribution'],
                'assessment_stats' => $report['assessment_stats'] ?? [],
            ],
        ]);
    }

    /**
     * Return a student's full profile and enrollment data for an offering.
     */
    public function studentProfile(CourseOffering $offering, string $identifier): JsonResponse
    {
        $this->authorize('view', $offering);

        $student = Student::where('student_id_number', $identifier)->first()
            ?? Student::whereRaw('LOWER(github_username) = ?', [strtolower($identifier)])->first();

        if (! $student) {
            return response()->json(['error' => "Student not found: {$identifier}"], 404);
        }

        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('course_offering_id', $offering->id)
            ->first();

        if (! $enrollment) {
            return response()->json(['error' => "Student {$identifier} is not enrolled in this offering."], 404);
        }

        return response()->json([
            'data' => [
                'student' => [
                    'student_id_number' => $student->student_id_number,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'email' => $student->email,
                    'personal_email' => $student->personal_email,
                    'github_username' => $student->github_username,
                    'gender' => $student->gender,
                    'program' => $student->program,
                    'year_of_study' => $student->year_of_study,
                    'study_mode' => $student->study_mode,
                    'is_registered' => $student->isRegistered(),
                ],
                'enrollment' => [
                    'status' => $enrollment->status,
                    'source' => $enrollment->source,
                    'ca_total' => $enrollment->ca_total,
                    'exam_score' => $enrollment->exam_score,
                    'final_total' => $enrollment->final_total,
                    'final_grade' => $enrollment->final_grade,
                    'grade_points' => $enrollment->grade_points,
                    'remarks' => $enrollment->remarks,
                    'enrolled_at' => $enrollment->created_at->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Sync enrollments: enroll new students and flag those not in the incoming list.
     */
    public function syncEnrollments(Request $request, CourseOffering $offering): JsonResponse
    {
        $this->authorize('update', $offering);

        $validated = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'required|string',
            'source' => 'nullable|string',
        ]);

        $source = $validated['source'] ?? 'moodle_sync';
        $incomingIds = collect($validated['student_ids']);

        $enrolled = 0;
        $notFound = [];

        foreach ($incomingIds as $studentIdNumber) {
            $student = Student::where('student_id_number', $studentIdNumber)->first();

            if (! $student) {
                $notFound[] = $studentIdNumber;

                continue;
            }

            $exists = Enrollment::where('student_id', $student->id)
                ->where('course_offering_id', $offering->id)
                ->exists();

            if (! $exists) {
                Enrollment::create([
                    'student_id' => $student->id,
                    'course_offering_id' => $offering->id,
                    'source' => $source,
                    'status' => 'enrolled',
                ]);
                $enrolled++;
            }
        }

        // Find students enrolled in gradebook but NOT in the incoming list
        $incomingStudentIds = Student::whereIn('student_id_number', $incomingIds)
            ->pluck('id');

        $extraEnrollments = Enrollment::where('course_offering_id', $offering->id)
            ->whereNotIn('student_id', $incomingStudentIds)
            ->with('student')
            ->get();

        $extraStudents = $extraEnrollments->map(fn ($e) => [
            'student_id_number' => $e->student->student_id_number,
            'name' => $e->student->first_name.' '.$e->student->last_name,
            'status' => $e->status,
        ])->values();

        return response()->json([
            'data' => [
                'enrolled' => $enrolled,
                'not_found' => $notFound,
                'not_in_source' => $extraStudents,
            ],
        ]);
    }

    /**
     * Export all grades as a downloadable Excel file.
     */
    public function export(CourseOffering $offering): BinaryFileResponse
    {
        $this->authorize('view', $offering);

        $code = $offering->course->code ?? 'export';
        $filename = "{$code}_grade_sheet.xlsx";

        return Excel::download(new GradeSheetExport($offering), $filename);
    }

    /**
     * Return the audit changelog for an offering.
     */
    public function changelog(CourseOffering $offering): JsonResponse
    {
        $this->authorize('view', $offering);

        $enrollmentIds = $offering->enrollments()->pluck('id');

        $gradeResultIds = GradeResult::whereIn('enrollment_id', $enrollmentIds)
            ->pluck('id');

        $logs = GradeAuditLog::query()
            ->where(function ($query) use ($enrollmentIds, $gradeResultIds) {
                $query->where(function ($q) use ($gradeResultIds) {
                    $q->where('auditable_type', GradeResult::class)
                        ->whereIn('auditable_id', $gradeResultIds);
                })->orWhere(function ($q) use ($enrollmentIds) {
                    $q->where('auditable_type', Enrollment::class)
                        ->whereIn('auditable_id', $enrollmentIds);
                });
            })
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json([
            'data' => $logs->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'auditable_type' => class_basename($log->auditable_type),
                'auditable_id' => $log->auditable_id,
                'user' => $log->user?->name,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'reason' => $log->reason,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Create a student and optionally enroll them in an offering.
     */
    public function createStudent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id_number' => 'required|string|max:20',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'gender' => 'nullable|string|in:Male,Female',
            'program' => 'nullable|string|max:255',
            'year_of_study' => 'nullable|integer|min:1|max:7',
            'study_mode' => 'nullable|string|max:50',
            'github_username' => 'nullable|string|max:39',
            'offering_id' => 'nullable|exists:course_offerings,id',
        ]);

        $existing = Student::where('student_id_number', $validated['student_id_number'])->first();

        if ($existing) {
            // Update existing student with any new fields
            $updates = collect($validated)
                ->except(['student_id_number', 'offering_id'])
                ->filter(fn ($value) => $value !== null)
                ->all();

            if (! empty($updates)) {
                $existing->update($updates);
            }

            $student = $existing->fresh();
            $created = false;
        } else {
            // Check email uniqueness
            if (Student::whereRaw('LOWER(email) = ?', [strtolower($validated['email'])])->exists()) {
                return response()->json(['error' => "Email '{$validated['email']}' is already in use by another student."], 422);
            }

            $student = Student::create(collect($validated)->except(['offering_id'])->all());
            $created = true;
        }

        $enrolled = false;
        if (! empty($validated['offering_id'])) {
            $exists = Enrollment::where('student_id', $student->id)
                ->where('course_offering_id', $validated['offering_id'])
                ->exists();

            if (! $exists) {
                Enrollment::create([
                    'student_id' => $student->id,
                    'course_offering_id' => $validated['offering_id'],
                    'source' => 'api',
                    'status' => 'enrolled',
                ]);
                $enrolled = true;
            }
        }

        return response()->json([
            'data' => [
                'student_id_number' => $student->student_id_number,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'email' => $student->email,
                'github_username' => $student->github_username,
                'gender' => $student->gender,
                'program' => $student->program,
                'year_of_study' => $student->year_of_study,
                'created' => $created,
                'enrolled' => $enrolled,
            ],
        ], $created ? 201 : 200);
    }

    /**
     * Bulk create students and optionally enroll them in an offering.
     */
    public function bulkCreateStudents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'students' => 'required|array|min:1',
            'students.*.student_id_number' => 'required|string|max:20',
            'students.*.first_name' => 'required|string|max:255',
            'students.*.last_name' => 'required|string|max:255',
            'students.*.email' => 'required|email|max:255',
            'students.*.gender' => 'nullable|string|in:Male,Female',
            'students.*.program' => 'nullable|string|max:255',
            'students.*.year_of_study' => 'nullable|integer|min:1|max:7',
            'students.*.github_username' => 'nullable|string|max:39',
            'offering_id' => 'nullable|exists:course_offerings,id',
        ]);

        $created = 0;
        $updated = 0;
        $enrolled = 0;
        $errors = [];

        foreach ($validated['students'] as $index => $data) {
            $existing = Student::where('student_id_number', $data['student_id_number'])->first();

            if ($existing) {
                $updates = collect($data)
                    ->except(['student_id_number'])
                    ->filter(fn ($value) => $value !== null)
                    ->all();

                if (! empty($updates)) {
                    $existing->update($updates);
                }

                $student = $existing;
                $updated++;
            } else {
                if (Student::whereRaw('LOWER(email) = ?', [strtolower($data['email'])])->exists()) {
                    $errors[] = "Row {$index}: Email '{$data['email']}' already in use.";

                    continue;
                }

                $student = Student::create($data);
                $created++;
            }

            if (! empty($validated['offering_id'])) {
                $exists = Enrollment::where('student_id', $student->id)
                    ->where('course_offering_id', $validated['offering_id'])
                    ->exists();

                if (! $exists) {
                    Enrollment::create([
                        'student_id' => $student->id,
                        'course_offering_id' => $validated['offering_id'],
                        'source' => 'api',
                        'status' => 'enrolled',
                    ]);
                    $enrolled++;
                }
            }
        }

        return response()->json([
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'enrolled' => $enrolled,
                'errors' => $errors,
            ],
        ]);
    }

    /**
     * Reassign a GitHub username from one student to another.
     */
    public function resolveGithub(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'github_username' => 'required|string|max:39',
            'correct_student_id' => 'required|string',
            'resolution_notes' => 'nullable|string|max:1000',
        ]);

        $username = trim($validated['github_username']);
        $correctStudent = Student::where('student_id_number', $validated['correct_student_id'])->first();

        if (! $correctStudent) {
            return response()->json(['error' => "Student not found: {$validated['correct_student_id']}"], 404);
        }

        $currentHolder = Student::where('github_username', $username)->first();

        if (! $currentHolder) {
            // No conflict — just assign it
            $correctStudent->update(['github_username' => $username]);

            GradeAuditLog::create([
                'auditable_type' => Student::class,
                'auditable_id' => $correctStudent->id,
                'user_id' => auth()->id(),
                'action' => 'github_assigned',
                'old_values' => ['github_username' => $correctStudent->getOriginal('github_username')],
                'new_values' => ['github_username' => $username],
                'reason' => $validated['resolution_notes'] ?? null,
                'ip_address' => $request->ip(),
            ]);

            $backfill = app(BackfillLabGradesService::class)->backfillForStudent($correctStudent);

            return response()->json([
                'data' => [
                    'action' => 'assigned',
                    'student_id_number' => $correctStudent->student_id_number,
                    'github_username' => $username,
                    'previous_holder' => null,
                    'grades_backfilled' => $backfill['grades_created'] ?? 0,
                ],
            ]);
        }

        if ($currentHolder->id === $correctStudent->id) {
            // Still resolve any pending disputes for this username
            UsernameDispute::where('github_username', $username)
                ->where('status', 'pending')
                ->update([
                    'status' => 'resolved',
                    'resolved_by' => auth()->id(),
                    'resolved_at' => now(),
                    'resolution_notes' => $validated['resolution_notes'] ?? 'Confirmed current owner is correct.',
                ]);

            return response()->json([
                'data' => [
                    'action' => 'no_change',
                    'message' => 'This student already owns this GitHub username. Pending disputes resolved.',
                ],
            ]);
        }

        // Reassign: clear from current holder, assign to correct student
        $previousHolder = $currentHolder->student_id_number;

        GradeAuditLog::create([
            'auditable_type' => Student::class,
            'auditable_id' => $currentHolder->id,
            'user_id' => auth()->id(),
            'action' => 'github_removed',
            'old_values' => ['github_username' => $username],
            'new_values' => ['github_username' => null],
            'reason' => "Reassigned to {$correctStudent->student_id_number}. ".($validated['resolution_notes'] ?? ''),
            'ip_address' => $request->ip(),
        ]);

        $currentHolder->update(['github_username' => null]);
        $correctStudent->update(['github_username' => $username]);

        GradeAuditLog::create([
            'auditable_type' => Student::class,
            'auditable_id' => $correctStudent->id,
            'user_id' => auth()->id(),
            'action' => 'github_reassigned',
            'old_values' => ['github_username' => $correctStudent->getOriginal('github_username')],
            'new_values' => ['github_username' => $username],
            'reason' => "Reassigned from {$previousHolder}. ".($validated['resolution_notes'] ?? ''),
            'ip_address' => $request->ip(),
        ]);

        // Resolve any pending disputes for this username
        UsernameDispute::where('github_username', $username)
            ->where('status', 'pending')
            ->update([
                'status' => 'resolved',
                'resolved_by' => auth()->id(),
                'resolved_at' => now(),
                'resolution_notes' => $validated['resolution_notes'] ?? 'Resolved via API.',
            ]);

        $backfill = app(BackfillLabGradesService::class)->backfillForStudent($correctStudent);

        return response()->json([
            'data' => [
                'action' => 'reassigned',
                'student_id_number' => $correctStudent->student_id_number,
                'github_username' => $username,
                'previous_holder' => $previousHolder,
                'grades_backfilled' => $backfill['grades_created'] ?? 0,
            ],
        ]);
    }

    /**
     * List pending username disputes.
     */
    public function listDisputes(Request $request): JsonResponse
    {
        $disputes = UsernameDispute::with(['claimant', 'currentHolder', 'courseOffering.course'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $disputes->map(fn ($d) => [
                'id' => $d->id,
                'github_username' => $d->github_username,
                'claimant' => [
                    'student_id_number' => $d->claimant->student_id_number,
                    'name' => $d->claimant->first_name.' '.$d->claimant->last_name,
                ],
                'current_holder' => [
                    'student_id_number' => $d->currentHolder->student_id_number,
                    'name' => $d->currentHolder->first_name.' '.$d->currentHolder->last_name,
                ],
                'course' => $d->courseOffering?->course->code,
                'created_at' => $d->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Find or create an assessment for the offering.
     */
    private function resolveAssessment(CourseOffering $offering, string $name): Assessment
    {
        // Look for existing assessment by name in this offering's groups
        $existing = Assessment::whereHas('assessmentGroup', fn ($q) => $q->where('course_offering_id', $offering->id))
            ->where('name', $name)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Find or create the "Labs" CA group
        $group = AssessmentGroup::firstOrCreate(
            [
                'course_offering_id' => $offering->id,
                'name' => 'Labs',
                'type' => 'ca',
            ],
            [
                'weight_percentage' => 0,
                'weight_mode' => 'percentage',
                'sort_order' => 1,
            ]
        );

        // Create new assessment
        $sortOrder = $group->assessments()->count() + 1;

        return Assessment::create([
            'name' => $name,
            'course_id' => $offering->course_id,
            'assessment_group_id' => $group->id,
            'weight' => 1,
            'max_raw_score' => 100,
            'normalized_to' => 10,
            'has_subsections' => true,
            'is_published' => false,
            'sort_order' => $sortOrder,
        ]);
    }
}
