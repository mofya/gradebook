<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\EnrollmentTrendsChart;
use App\Filament\Widgets\GradeDistributionChart;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeAuditLog;
use App\Models\GradeQuery;
use App\Models\Student;
use App\Models\UsernameDispute;
use App\Models\Year;
use Filament\Pages\Dashboard as BaseDashboard;

class AdminDashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.admin-dashboard';

    public function getFooterWidgets(): array
    {
        return [
            GradeDistributionChart::class,
            EnrollmentTrendsChart::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 2;
    }

    public function getViewData(): array
    {
        $currentYear = Year::current()->first();

        $currentSemesterIds = $currentYear
            ? $currentYear->semesters()->pluck('id')->toArray()
            : [];

        $currentOfferings = CourseOffering::whereIn('semester_id', $currentSemesterIds)->get();
        $currentOfferingIds = $currentOfferings->pluck('id')->toArray();

        $totalStudents = Student::count();
        $currentEnrollments = Enrollment::whereIn('course_offering_id', $currentOfferingIds)->count();
        $studentsWithGrades = Enrollment::whereIn('course_offering_id', $currentOfferingIds)
            ->whereNotNull('ca_total')
            ->distinct('student_id')
            ->count('student_id');

        $openQueries = GradeQuery::whereNotIn('status', ['resolved', 'rejected'])->count();

        $offeringStats = $currentOfferings->map(function (CourseOffering $offering) {
            $offering->loadMissing(['course', 'semester.year', 'enrollments']);
            $enrollments = $offering->enrollments;
            $graded = $enrollments->whereNotNull('ca_total');
            $avg = $graded->isNotEmpty() ? round($graded->avg('ca_total'), 1) : null;

            return [
                'id' => $offering->id,
                'code' => $offering->course->code,
                'name' => $offering->course->name,
                'semester' => ($offering->semester->year->name ?? '').' '.$offering->semester->name,
                'enrolled' => $enrollments->count(),
                'graded' => $graded->count(),
                'average' => $avg,
                'status' => $offering->status?->value ?? 'active',
            ];
        });

        $recentActivity = GradeAuditLog::with('user')
            ->latest()
            ->limit(8)
            ->get()
            ->map(function (GradeAuditLog $log) {
                $oldVals = is_array($log->old_values) ? $log->old_values : [];
                $newVals = is_array($log->new_values) ? $log->new_values : [];
                $keys = array_filter(
                    array_unique(array_merge(array_keys($oldVals), array_keys($newVals))),
                    fn ($k) => ! in_array($k, ['updated_at', 'created_at'])
                );

                $summary = collect($keys)->map(function ($key) use ($oldVals, $newVals, $log) {
                    $old = $oldVals[$key] ?? null;
                    $new = $newVals[$key] ?? null;
                    $label = str_replace('_', ' ', $key);

                    if ($log->action === 'created') {
                        return "{$label}: {$new}";
                    }

                    return "{$label}: {$old} → {$new}";
                })->implode(', ');

                return [
                    'time' => $log->created_at->diffForHumans(),
                    'user' => $log->user?->name ?? 'System',
                    'action' => $log->action,
                    'record' => class_basename($log->auditable_type).' #'.$log->auditable_id,
                    'summary' => $summary ?: 'Timestamp update',
                ];
            });

        $pendingQueries = GradeQuery::with(['student', 'enrollment.courseOffering.course'])
            ->whereNotIn('status', ['resolved', 'rejected'])
            ->latest()
            ->limit(5)
            ->get();

        $pendingDisputes = UsernameDispute::with(['claimant', 'currentHolder', 'courseOffering.course'])
            ->where('status', 'pending')
            ->latest()
            ->limit(5)
            ->get();

        return [
            'totalStudents' => $totalStudents,
            'currentEnrollments' => $currentEnrollments,
            'studentsWithGrades' => $studentsWithGrades,
            'openQueries' => $openQueries,
            'pendingDisputeCount' => $pendingDisputes->count(),
            'currentYear' => $currentYear,
            'offeringStats' => $offeringStats,
            'recentActivity' => $recentActivity,
            'pendingQueries' => $pendingQueries,
            'pendingDisputes' => $pendingDisputes,
        ];
    }
}
