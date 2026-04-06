<?php

namespace App\Services\Import;

class CourseDataHeaderParser
{
    /**
     * Auto-detect column roles from header row.
     *
     * @param  array<int, mixed>  $headers
     * @return array<int, array{index: int, header: string, detected_role: string, confirmed_role: string, assessment_name: string|null, max_score: float|null}>
     */
    public function parseHeaders(array $headers): array
    {
        $mappings = [];

        foreach ($headers as $index => $rawHeader) {
            $header = trim((string) $rawHeader);
            $lowerHeader = strtolower($header);

            $role = $this->detectRole($lowerHeader, $header);
            $assessmentName = null;
            $maxScore = null;

            if ($role === 'ca_assessment') {
                $parsed = $this->parseAssessmentHeader($header);
                $assessmentName = $parsed['name'];
                $maxScore = $parsed['max_score'];
            }

            if ($role === 'exam_score') {
                $maxScore = $this->parseExamDenominator($header);
            }

            $mappings[] = [
                'index' => $index,
                'header' => $header,
                'detected_role' => $role,
                'confirmed_role' => $role,
                'assessment_name' => $assessmentName,
                'max_score' => $maxScore,
            ];
        }

        return $mappings;
    }

    /**
     * Detect the role of a column based on its header text.
     */
    public function detectRole(string $lowerHeader, string $originalHeader): string
    {
        if (str_starts_with(trim($originalHeader), '=')) {
            return 'skip';
        }

        if (preg_match('/^(no\.?|#|s\/n|s\.?n\.?)$/i', $lowerHeader)) {
            return 'skip';
        }

        if (preg_match('/student.?(id|number|no|num)|id.?number|comp.?no|computer.?number|matric|stud.?no/i', $lowerHeader)) {
            return 'student_id';
        }

        if (preg_match('/^(first.?name|given.?name|fname|other.?names?)$/i', $lowerHeader)) {
            return 'first_name';
        }

        if (preg_match('/^(last.?name|surname|family.?name|lname)$/i', $lowerHeader)) {
            return 'last_name';
        }

        if (preg_match('/^(full.?name|student.?name|name)$/i', $lowerHeader)) {
            return 'full_name';
        }

        if (preg_match('/e-?mail/i', $lowerHeader)) {
            return 'email';
        }

        if (preg_match('/^(gender|sex)$/i', $lowerHeader)) {
            return 'gender';
        }

        if (preg_match('/^(program|programme|prog|course.?of.?study)$/i', $lowerHeader)) {
            return 'program';
        }

        if (preg_match('/^(year.?of.?study|study.?year|year|level)$/i', $lowerHeader)) {
            return 'year_of_study';
        }

        if (preg_match('/^(ca\s*[\/(]\s*\d+\)?|ca\s*grade|ca\s*total|course\s*total|course\s*grade|exam\s*grade|final\s*mark|final\s*grade|grade|gp|grade\s*point|total|remark|comment|note|class|result|check(\s*digit)?|def(erred)?|sup(plementary)?|rank|position|pass|fail|status|average|mean|cumulative|credits|points)$/i', $lowerHeader)) {
            return 'skip';
        }

        if (preg_match('/^exam/i', $lowerHeader)) {
            return 'exam_score';
        }

        if (preg_match('/[\(\)\/]\s*\d+/i', $originalHeader)) {
            return 'ca_assessment';
        }

        if ($lowerHeader === '') {
            return 'skip';
        }

        return 'ca_assessment';
    }

    /**
     * Parse an assessment header like "Quiz 1 (30)" or "Assignment 1/20" to extract name and max score.
     *
     * @return array{name: string, max_score: float|null}
     */
    public function parseAssessmentHeader(string $header): array
    {
        if (preg_match('/^(.+?)\s*\((\d+(?:\.\d+)?)\)\s*$/', $header, $matches)) {
            return [
                'name' => trim($matches[1]),
                'max_score' => (float) $matches[2],
            ];
        }

        if (preg_match('/^(.+?)\s*\/\s*(\d+(?:\.\d+)?)\s*$/', $header, $matches)) {
            return [
                'name' => trim($matches[1]),
                'max_score' => (float) $matches[2],
            ];
        }

        return [
            'name' => trim($header),
            'max_score' => null,
        ];
    }

    /**
     * Parse exam header to extract denominator, e.g. "Exam/60" → 60, "Exam (100)" → 100.
     */
    public function parseExamDenominator(string $header): ?float
    {
        if (preg_match('/[\(\/]\s*(\d+(?:\.\d+)?)\s*[\)]?\s*$/', $header, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }

    /**
     * Auto-select the best worksheet from a list of sheet names.
     *
     * @param  array<int, string>  $sheetNames
     */
    public function autoSelectSheet(array $sheetNames, ?string $courseCode = null): ?string
    {
        if (count($sheetNames) <= 1) {
            return $sheetNames[0] ?? null;
        }

        $reportPatterns = [
            '/grade\s*summary/i',
            '/gender\s*analysis/i',
            '/chart/i',
            '/summary/i',
            '/report/i',
            '/statistics/i',
            '/analysis/i',
        ];

        $nonReportSheets = [];

        foreach ($sheetNames as $name) {
            $isReport = false;
            foreach ($reportPatterns as $pattern) {
                if (preg_match($pattern, $name)) {
                    $isReport = true;

                    break;
                }
            }

            if (! $isReport) {
                $nonReportSheets[] = $name;
            }
        }

        foreach ($nonReportSheets as $name) {
            if (strcasecmp($name, 'Data') === 0) {
                return $name;
            }
        }

        if ($courseCode) {
            foreach ($nonReportSheets as $name) {
                if (stripos($name, $courseCode) !== false) {
                    return $name;
                }
            }
        }

        return $nonReportSheets[0] ?? $sheetNames[0];
    }

    /**
     * Identify which sheets appear to be report/summary sheets.
     *
     * @param  array<int, string>  $sheetNames
     * @return array<string, bool> Sheet name → is report sheet
     */
    public function flagReportSheets(array $sheetNames): array
    {
        $reportPatterns = [
            '/grade\s*summary/i',
            '/gender\s*analysis/i',
            '/chart/i',
            '/summary/i',
            '/report/i',
            '/statistics/i',
            '/analysis/i',
        ];

        $flags = [];

        foreach ($sheetNames as $name) {
            $isReport = false;
            foreach ($reportPatterns as $pattern) {
                if (preg_match($pattern, $name)) {
                    $isReport = true;

                    break;
                }
            }
            $flags[$name] = $isReport;
        }

        return $flags;
    }
}
