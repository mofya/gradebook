<?php

namespace App\Services;

use App\Enums\ExamStatus;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradingScheme;

class GradingService
{
    /**
     * UNZA Grading Scale.
     *
     * @var array<int, array{min: int, max: int, letter: string, points: float}>
     */
    protected const GRADING_SCALE = [
        ['min' => 90, 'max' => 100, 'letter' => 'A+', 'points' => 4.0],
        ['min' => 80, 'max' => 89, 'letter' => 'A', 'points' => 4.0],
        ['min' => 70, 'max' => 79, 'letter' => 'B+', 'points' => 3.5],
        ['min' => 60, 'max' => 69, 'letter' => 'B', 'points' => 3.0],
        ['min' => 50, 'max' => 59, 'letter' => 'C+', 'points' => 2.5],
        ['min' => 40, 'max' => 49, 'letter' => 'C', 'points' => 2.0],
        ['min' => 35, 'max' => 39, 'letter' => 'D+', 'points' => 1.5],
        ['min' => 0, 'max' => 34, 'letter' => 'D', 'points' => 1.0],
    ];

    /**
     * Get the letter grade for a numeric mark.
     */
    public function getLetterGrade(float $mark): string
    {
        $mark = round($mark);

        foreach (self::GRADING_SCALE as $range) {
            if ($mark >= $range['min'] && $mark <= $range['max']) {
                return $range['letter'];
            }
        }

        return 'D';
    }

    /**
     * Get grade points for a numeric mark.
     */
    public function getGradePoints(float $mark): float
    {
        $mark = round($mark);

        foreach (self::GRADING_SCALE as $range) {
            if ($mark >= $range['min'] && $mark <= $range['max']) {
                return $range['points'];
            }
        }

        return 1.0;
    }

    /**
     * Validate that a mark is within the allowed range (0-100).
     */
    public function isValidMark(float $mark): bool
    {
        return $mark >= 0 && $mark <= 100;
    }

    /**
     * Calculate semester GPA given an array of course results.
     *
     * @param  array<int, array{mark: float, credits: int}>  $courseResults
     */
    public function calculateSemesterGpa(array $courseResults): float
    {
        $totalCredits = 0;
        $totalPoints = 0.0;

        foreach ($courseResults as $result) {
            $gradePoints = $this->getGradePoints($result['mark']);
            $totalPoints += $gradePoints * $result['credits'];
            $totalCredits += $result['credits'];
        }

        if ($totalCredits === 0) {
            return 0.0;
        }

        return round($totalPoints / $totalCredits, 2);
    }

    /**
     * Calculate cumulative GPA across all semesters.
     *
     * @param  array<int, array{mark: float, credits: int}>  $allCourseResults
     */
    public function calculateCumulativeGpa(array $allCourseResults): float
    {
        return $this->calculateSemesterGpa($allCourseResults);
    }

    /**
     * Get letter grade using a DB-driven grading scheme.
     * Falls back to hardcoded scale if no scheme provided.
     */
    public function getLetterGradeFromScheme(float $mark, ?GradingScheme $scheme = null): string
    {
        if ($scheme) {
            return $scheme->getLetterGrade($mark);
        }

        return $this->getLetterGrade($mark);
    }

    /**
     * Get grade points using a DB-driven grading scheme.
     * Falls back to hardcoded scale if no scheme provided.
     */
    public function getGradePointsFromScheme(float $mark, ?GradingScheme $scheme = null): float
    {
        if ($scheme) {
            return $scheme->getGradePoints($mark);
        }

        return $this->getGradePoints($mark);
    }

    /**
     * Compute the CA total for an enrollment by summing normalized assessment scores within CA groups.
     * Respects ca_override when set.
     */
    public function computeCaTotal(Enrollment $enrollment): ?float
    {
        if ($enrollment->ca_override !== null) {
            return (float) $enrollment->ca_override;
        }

        $enrollment->loadMissing([
            'courseOffering.assessmentGroups.assessments',
            'gradeResults.assessment',
        ]);

        $offering = $enrollment->courseOffering;
        $caGroups = $offering->assessmentGroups->where('type', 'ca');

        if ($caGroups->isEmpty()) {
            return null;
        }

        $gradeResultsByAssessment = $enrollment->gradeResults->keyBy('assessment_id');
        $total = 0.0;

        foreach ($caGroups as $group) {
            $groupTotal = $this->computeGroupTotal($group, $gradeResultsByAssessment);
            $total += $groupTotal;
        }

        return round($total, 2);
    }

    /**
     * Compute the total for a single assessment group, respecting its aggregation mode.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\GradeResult>  $gradeResultsByAssessment
     */
    protected function computeGroupTotal(\App\Models\AssessmentGroup $group, \Illuminate\Support\Collection $gradeResultsByAssessment): float
    {
        $scores = [];

        foreach ($group->assessments as $assessment) {
            $result = $gradeResultsByAssessment->get($assessment->id);

            if ($result && ! $result->is_excused && $result->raw_score !== null) {
                $normalized = $result->normalized_score ?? $result->calculateNormalizedScore();
                $scores[] = (float) ($normalized ?? 0);
            }
        }

        if (empty($scores)) {
            return 0.0;
        }

        $mode = $group->aggregation_mode ?? 'WEIGHTED_AVERAGE';

        return match ($mode) {
            'MAX' => max($scores),
            'DROP_LOWEST' => $this->computeDropLowest($scores, (int) ($group->drop_count ?? 0)),
            default => array_sum($scores),
        };
    }

    /**
     * Drop the N lowest scores, then average the remaining.
     *
     * @param  array<int, float>  $scores
     */
    protected function computeDropLowest(array $scores, int $dropCount): float
    {
        if ($dropCount <= 0 || $dropCount >= count($scores)) {
            return array_sum($scores);
        }

        sort($scores);
        $kept = array_slice($scores, $dropCount);

        if (empty($kept)) {
            return 0.0;
        }

        return array_sum($kept) / count($kept) * count($scores);
    }

    /**
     * Compute the exam total for an enrollment by summing scores in exam groups.
     */
    public function computeExamTotal(Enrollment $enrollment): ?float
    {
        $enrollment->loadMissing([
            'courseOffering.assessmentGroups.assessments',
            'gradeResults.assessment',
        ]);

        $offering = $enrollment->courseOffering;
        $examGroups = $offering->assessmentGroups->where('type', 'exam');

        if ($examGroups->isEmpty()) {
            return $enrollment->exam_score !== null ? (float) $enrollment->exam_score : null;
        }

        $gradeResultsByAssessment = $enrollment->gradeResults->keyBy('assessment_id');
        $total = 0.0;
        $hasAnyScore = false;

        foreach ($examGroups as $group) {
            foreach ($group->assessments as $assessment) {
                $result = $gradeResultsByAssessment->get($assessment->id);

                if ($result && ! $result->is_excused && $result->raw_score !== null) {
                    $normalized = $result->normalized_score ?? $result->calculateNormalizedScore();
                    $total += (float) ($normalized ?? 0);
                    $hasAnyScore = true;
                }
            }
        }

        if (! $hasAnyScore) {
            return $enrollment->exam_score !== null ? (float) $enrollment->exam_score : null;
        }

        return round($total, 2);
    }

    /**
     * Compute the final mark: (ca_total * ca_weight + exam_total * exam_weight) / 100.
     * Respects final_override when set.
     */
    public function computeFinalMark(Enrollment $enrollment): ?float
    {
        if ($enrollment->final_override !== null) {
            return (float) $enrollment->final_override;
        }

        $caTotal = $this->computeCaTotal($enrollment);
        $examTotal = $this->computeExamTotal($enrollment);

        if ($caTotal === null && $examTotal === null) {
            return null;
        }

        $enrollment->loadMissing('courseOffering');
        $offering = $enrollment->courseOffering;

        $caWeight = (float) ($offering->ca_weight ?? 50);
        $examWeight = (float) ($offering->exam_weight ?? 50);

        $ca = $caTotal ?? 0.0;
        $exam = $examTotal ?? 0.0;

        $finalMark = ($ca * $caWeight + $exam * $examWeight) / 100;

        return round($finalMark, 2);
    }

    /**
     * Resolve the grade for an enrollment: compute final mark → letter grade → grade points → write back.
     * Handles special exam statuses: SP (supplementary cap), DV (deferred), EX (exempt), ABS (absent).
     */
    public function resolveGrade(Enrollment $enrollment): Enrollment
    {
        $enrollment->loadMissing('courseOffering.gradingScheme.levels');

        $examStatus = $enrollment->exam_status;

        // Handle deferred: skip computation
        if ($examStatus === ExamStatus::Deferred) {
            $enrollment->update([
                'final_total' => null,
                'final_grade' => null,
                'grade_points' => null,
                'remarks' => 'Deferred',
            ]);

            return $enrollment->refresh();
        }

        // Handle exempt: skip computation
        if ($examStatus === ExamStatus::Exempt) {
            $enrollment->update([
                'final_total' => null,
                'final_grade' => null,
                'grade_points' => null,
                'remarks' => 'Exempt',
            ]);

            return $enrollment->refresh();
        }

        // Handle withheld: result withheld (e.g. unpaid fees)
        if ($examStatus === ExamStatus::Withheld) {
            $enrollment->update([
                'final_total' => null,
                'final_grade' => 'WH',
                'grade_points' => null,
                'remarks' => 'Withheld',
            ]);

            return $enrollment->refresh();
        }

        // Handle absent: set final mark to 0
        if ($examStatus === ExamStatus::Absent) {
            $enrollment->update([
                'ca_total' => $this->computeCaTotal($enrollment),
                'exam_score' => 0,
                'final_total' => 0,
                'final_grade' => 'NE',
                'grade_points' => 0.0,
                'remarks' => 'Absent',
            ]);

            return $enrollment->refresh();
        }

        $caTotal = $this->computeCaTotal($enrollment);
        $examTotal = $this->computeExamTotal($enrollment);
        $finalMark = $this->computeFinalMark($enrollment);

        $scheme = $enrollment->courseOffering->gradingScheme;

        $letterGrade = null;
        $gradePoints = null;
        $remarks = null;

        if ($finalMark !== null) {
            // Handle supplementary: cap at 50% (D+)
            if ($examStatus === ExamStatus::Supplementary && $finalMark > 50) {
                $finalMark = 50.0;
                $remarks = '(SP)';
            }

            $letterGrade = $this->getLetterGradeFromScheme($finalMark, $scheme);
            $gradePoints = $this->getGradePointsFromScheme($finalMark, $scheme);
        }

        $enrollment->update([
            'ca_total' => $caTotal,
            'exam_score' => $examTotal ?? $enrollment->exam_score,
            'final_total' => $finalMark,
            'final_grade' => $letterGrade,
            'grade_points' => $gradePoints,
            'remarks' => $remarks,
        ]);

        return $enrollment->refresh();
    }

    /**
     * Bulk resolve all grades for a course offering.
     */
    public function resolveAllGrades(CourseOffering $courseOffering): int
    {
        $courseOffering->load([
            'assessmentGroups.assessments',
            'gradingScheme.levels',
            'enrollments.gradeResults.assessment',
        ]);

        $count = 0;

        foreach ($courseOffering->enrollments as $enrollment) {
            $this->resolveGrade($enrollment);
            $count++;
        }

        return $count;
    }
}
