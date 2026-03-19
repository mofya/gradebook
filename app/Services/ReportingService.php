<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeResult;
use Illuminate\Support\Collection;

class ReportingService
{
    public function __construct(
        protected GradingService $gradingService
    ) {}

    /**
     * Generate a class report for a course offering.
     *
     * @return array<string, mixed>
     */
    public function generateClassReport(CourseOffering $courseOffering): array
    {
        $courseOffering->load([
            'course',
            'semester.year',
            'lecturer',
            'enrollments.student',
        ]);

        $enrollments = $courseOffering->enrollments;

        $totalStudents = $enrollments->count();
        $gradedStudents = $enrollments->filter(fn ($e) => $e->final_total !== null);
        $eligibleStudents = $this->filterEligibleStudents($gradedStudents);

        $stats = [
            'total_enrolled' => $totalStudents,
            'graded' => $gradedStudents->count(),
            'pending' => $totalStudents - $gradedStudents->count(),
        ];

        if ($eligibleStudents->isNotEmpty()) {
            $marks = $eligibleStudents->pluck('final_total')->map(fn ($m) => (float) $m);
            $stats['average'] = round($marks->avg(), 2);
            $stats['highest'] = round($marks->max(), 2);
            $stats['lowest'] = round($marks->min(), 2);
            $stats['median'] = round($this->calculateMedian($marks), 2);
            $stats['std_deviation'] = round($this->calculateStdDev($marks), 2);
            $courseStats = $this->computeCourseStatistics($eligibleStudents);
            $stats['pass_rate'] = $courseStats['pass_rate'];
            $stats['pass_count'] = $courseStats['pass_count'];
            $stats['fail_count'] = $courseStats['fail_count'];
        } else {
            $stats['average'] = 0;
            $stats['highest'] = 0;
            $stats['lowest'] = 0;
            $stats['median'] = 0;
            $stats['std_deviation'] = 0;
            $stats['pass_rate'] = 0;
            $stats['pass_count'] = 0;
            $stats['fail_count'] = 0;
        }

        return [
            'course_offering' => $courseOffering,
            'stats' => $stats,
            'enrollments' => $enrollments,
            'distribution' => $this->getGradeDistribution($gradedStudents),
        ];
    }

    /**
     * Get grade distribution counts.
     *
     * @return array<string, int>
     */
    public function getGradeDistribution(Collection $enrollments): array
    {
        $distribution = [
            'A+' => 0, 'A' => 0, 'B+' => 0, 'B' => 0,
            'C+' => 0, 'C' => 0, 'D+' => 0, 'D' => 0, 'NE' => 0,
        ];

        foreach ($enrollments as $enrollment) {
            if ($enrollment->final_grade && isset($distribution[$enrollment->final_grade])) {
                $distribution[$enrollment->final_grade]++;
            } elseif ($enrollment->final_total !== null) {
                $letter = $this->gradingService->getLetterGrade((float) $enrollment->final_total);
                if (isset($distribution[$letter])) {
                    $distribution[$letter]++;
                }
            }
        }

        return $distribution;
    }

    /**
     * Generate an offering report with per-assessment statistics.
     *
     * @return array<string, mixed>
     */
    public function generateOfferingReport(CourseOffering $courseOffering): array
    {
        $report = $this->generateClassReport($courseOffering);

        $courseOffering->load(['assessmentGroups.assessments']);

        $assessmentIds = $courseOffering->assessmentGroups
            ->flatMap(fn ($group) => $group->assessments->pluck('id'))
            ->all();

        $allResults = GradeResult::query()
            ->whereIn('assessment_id', $assessmentIds)
            ->whereHas('enrollment', fn ($q) => $q->where('course_offering_id', $courseOffering->id))
            ->where('is_excused', false)
            ->whereNotNull('raw_score')
            ->get(['assessment_id', 'raw_score'])
            ->groupBy('assessment_id');

        $assessmentStats = [];

        foreach ($courseOffering->assessmentGroups as $group) {
            foreach ($group->assessments as $assessment) {
                $results = ($allResults->get($assessment->id) ?? collect())
                    ->pluck('raw_score')
                    ->map(fn ($s) => (float) $s);

                $assessmentStats[] = [
                    'assessment_name' => $assessment->name,
                    'count' => $results->count(),
                    'average' => $results->isNotEmpty() ? round($results->avg(), 2) : 0,
                    'highest' => $results->isNotEmpty() ? round($results->max(), 2) : 0,
                    'lowest' => $results->isNotEmpty() ? round($results->min(), 2) : 0,
                    'max_raw_score' => (float) $assessment->max_raw_score,
                ];
            }
        }

        $report['assessment_stats'] = $assessmentStats;

        return $report;
    }

    /**
     * Get statistics for a specific assessment within an offering.
     *
     * @return array<string, mixed>
     */
    public function getAssessmentStats(CourseOffering $courseOffering, Assessment $assessment): array
    {
        $results = GradeResult::query()
            ->where('assessment_id', $assessment->id)
            ->whereHas('enrollment', fn ($q) => $q->where('course_offering_id', $courseOffering->id))
            ->where('is_excused', false)
            ->whereNotNull('raw_score')
            ->pluck('raw_score')
            ->map(fn ($s) => (float) $s);

        return [
            'assessment_name' => $assessment->name,
            'count' => $results->count(),
            'average' => $results->isNotEmpty() ? round($results->avg(), 2) : 0,
            'highest' => $results->isNotEmpty() ? round($results->max(), 2) : 0,
            'lowest' => $results->isNotEmpty() ? round($results->min(), 2) : 0,
            'max_raw_score' => (float) $assessment->max_raw_score,
        ];
    }

    /**
     * Generate gender-disaggregated analysis for a course offering.
     *
     * @return array<string, mixed>
     */
    public function generateGenderAnalysis(CourseOffering $courseOffering): array
    {
        $courseOffering->load([
            'course',
            'semester.year',
            'lecturer',
            'enrollments.student',
        ]);

        $enrollments = $courseOffering->enrollments;
        $grouped = $enrollments->groupBy(fn ($e) => $e->student->gender ?? 'Unknown');

        $analysis = [];

        foreach ($grouped as $gender => $genderEnrollments) {
            $total = $genderEnrollments->count();
            $graded = $genderEnrollments->filter(fn ($e) => $e->final_total !== null);
            $eligible = $this->filterEligibleStudents($graded);
            $marks = $eligible->pluck('final_total')->map(fn ($m) => (float) $m);
            $courseStats = $this->computeCourseStatistics($eligible);

            $analysis[$gender] = [
                'total' => $total,
                'graded' => $eligible->count(),
                'average' => $marks->isNotEmpty() ? round($marks->avg(), 2) : 0,
                'highest' => $marks->isNotEmpty() ? round($marks->max(), 2) : 0,
                'lowest' => $marks->isNotEmpty() ? round($marks->min(), 2) : 0,
                'pass_count' => $courseStats['pass_count'],
                'fail_count' => $courseStats['fail_count'],
                'pass_rate' => $courseStats['pass_rate'],
                'distribution' => $this->getGradeDistribution($eligible),
            ];
        }

        return [
            'course_offering' => $courseOffering,
            'analysis' => $analysis,
        ];
    }

    /**
     * Grades that exclude a student from pass/fail denominator.
     */
    protected const EXCLUDED_GRADES = ['NE', 'DV', 'EX', 'ABS', 'WH'];

    /**
     * Grades that count as a pass.
     */
    protected const PASS_GRADES = ['A+', 'A', 'B+', 'B', 'C+', 'C'];

    /**
     * Filter enrollments to only those eligible for statistics (exclude special statuses).
     */
    protected function filterEligibleStudents(Collection $enrollments): Collection
    {
        return $enrollments->filter(
            fn (Enrollment $e) => $e->final_grade !== null
                && ! in_array($e->final_grade, self::EXCLUDED_GRADES, true)
        );
    }

    /**
     * Compute pass/fail statistics using grade-based logic.
     *
     * @return array{pass_count: int, fail_count: int, pass_rate: float}
     */
    public function computeCourseStatistics(Collection $eligibleEnrollments): array
    {
        $total = $eligibleEnrollments->count();

        if ($total === 0) {
            return ['pass_count' => 0, 'fail_count' => 0, 'pass_rate' => 0.0];
        }

        $passCount = $eligibleEnrollments->filter(
            fn (Enrollment $e) => in_array($e->final_grade, self::PASS_GRADES, true)
        )->count();

        $failCount = $total - $passCount;

        return [
            'pass_count' => $passCount,
            'fail_count' => $failCount,
            'pass_rate' => round(($passCount / $total) * 100, 1),
        ];
    }

    /**
     * Calculate median.
     */
    protected function calculateMedian(Collection $values): float
    {
        $sorted = $values->sort()->values();
        $count = $sorted->count();

        if ($count === 0) {
            return 0;
        }

        $middle = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return ($sorted[$middle - 1] + $sorted[$middle]) / 2;
        }

        return $sorted[$middle];
    }

    /**
     * Calculate standard deviation.
     */
    protected function calculateStdDev(Collection $values): float
    {
        $count = $values->count();

        if ($count <= 1) {
            return 0;
        }

        $mean = $values->avg();
        $squaredDiffs = $values->map(fn ($v) => pow($v - $mean, 2));

        return sqrt($squaredDiffs->sum() / ($count - 1));
    }
}
