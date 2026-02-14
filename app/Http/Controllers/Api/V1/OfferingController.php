<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use Illuminate\Http\JsonResponse;

class OfferingController extends Controller
{
    /**
     * List course offerings with basic info.
     */
    public function index(): JsonResponse
    {
        $offerings = CourseOffering::query()
            ->with(['course', 'semester.year', 'lecturer'])
            ->paginate(20);

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
     * Show a single course offering with enrollments and assessments.
     */
    public function show(CourseOffering $offering): JsonResponse
    {
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
        $offering->load('enrollments.student');

        return response()->json([
            'data' => $offering->enrollments->map(fn ($e) => [
                'id' => $e->id,
                'student_name' => $e->student->first_name.' '.$e->student->last_name,
                'student_id' => $e->student->student_id_number,
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
     * Get grade results for an offering.
     */
    public function grades(CourseOffering $offering): JsonResponse
    {
        $offering->load(['enrollments.gradeResults.assessment', 'enrollments.student']);

        $results = $offering->enrollments->flatMap(function ($enrollment) {
            return $enrollment->gradeResults->map(fn ($gr) => [
                'student_id' => $enrollment->student->student_id_number,
                'student_name' => $enrollment->student->first_name.' '.$enrollment->student->last_name,
                'assessment' => $gr->assessment->name,
                'raw_score' => $gr->raw_score,
                'normalized_score' => $gr->normalized_score,
                'is_excused' => $gr->is_excused,
            ]);
        });

        return response()->json(['data' => $results->values()]);
    }
}
