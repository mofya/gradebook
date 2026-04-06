<?php

namespace App\Services;

use App\Models\CourseOffering;
use App\Services\Import\CourseDataHeaderParser;
use App\Services\Import\CourseDataProcessor;
use App\Services\Import\CourseDataValidator;

class CourseDataImportService
{
    public function __construct(
        private CourseDataHeaderParser $headerParser,
        private CourseDataValidator $validator,
        private CourseDataProcessor $processor,
    ) {}

    /**
     * @param  array<int, array{index: int, header: string, detected_role: string, confirmed_role: string, assessment_name: string|null, max_score: float|null}>  $columnMappings
     * @return array{valid: bool, errors: array<int, string>}
     */
    public function validateColumnMappings(array $columnMappings): array
    {
        return $this->validator->validateColumnMappings($columnMappings);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, array{index: int, header: string, detected_role: string, confirmed_role: string, assessment_name: string|null, max_score: float|null}>  $columnMappings
     * @return array{valid: bool, errors: array<int, string>, warnings: array<int, string>}
     */
    public function preflight(array $rows, array $columnMappings): array
    {
        return $this->validator->preflight($rows, $columnMappings);
    }

    /**
     * @param  array<int, mixed>  $headers
     * @return array<int, array{index: int, header: string, detected_role: string, confirmed_role: string, assessment_name: string|null, max_score: float|null}>
     */
    public function parseHeaders(array $headers): array
    {
        return $this->headerParser->parseHeaders($headers);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, array{index: int, header: string, detected_role: string, confirmed_role: string, assessment_name: string|null, max_score: float|null}>  $columnMappings
     * @return array{students_created: int, students_found: int, enrollments_created: int, assessments_created: int, grades_imported: int, exam_scores_set: int, grades_resolved: int, errors: array<int, string>}
     */
    public function import(CourseOffering $courseOffering, array $rows, array $columnMappings, ?string $defaultProgram = null, ?int $defaultYearOfStudy = null): array
    {
        return $this->processor->import($courseOffering, $rows, $columnMappings, $defaultProgram, $defaultYearOfStudy);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, array{index: int, header: string, detected_role: string, confirmed_role: string, assessment_name: string|null, max_score: float|null}>  $columnMappings
     * @return array{rows: array<int, array<int, mixed>>, skipped: int}
     */
    public function filterDataRows(array $rows, array $columnMappings): array
    {
        return $this->processor->filterDataRows($rows, $columnMappings);
    }

    /**
     * @return array<int, int>
     */
    public function detectFormulaColumns(string $filePath, string $worksheetName, int $columnCount): array
    {
        return $this->processor->detectFormulaColumns($filePath, $worksheetName, $columnCount);
    }

    /**
     * @return array<string, float>|null
     */
    public function extractWeightsFromFormula(string $filePath, string $worksheetName, int $caColumnIndex): ?array
    {
        return $this->processor->extractWeightsFromFormula($filePath, $worksheetName, $caColumnIndex);
    }

    /**
     * @param  array<int, string>  $sheetNames
     */
    public function autoSelectSheet(array $sheetNames, ?string $courseCode = null): ?string
    {
        return $this->headerParser->autoSelectSheet($sheetNames, $courseCode);
    }

    /**
     * @param  array<int, string>  $sheetNames
     * @return array<string, bool>
     */
    public function flagReportSheets(array $sheetNames): array
    {
        return $this->headerParser->flagReportSheets($sheetNames);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, array{index: int, header: string, detected_role: string, confirmed_role: string, assessment_name: string|null, max_score: float|null}>  $columnMappings
     * @return array{valid: bool, errors: array<int, string>, warnings: array<int, string>, info: array<int, string>}
     */
    public function extendedPreflight(array $rows, array $columnMappings): array
    {
        return $this->validator->extendedPreflight($rows, $columnMappings);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, array{index: int, header: string, detected_role: string, confirmed_role: string, assessment_name: string|null, max_score: float|null}>  $columnMappings
     * @return array<int, array{index: int, header: string, detected_role: string, confirmed_role: string, assessment_name: string|null, max_score: float|null}>
     */
    public function inferMaxScores(array $rows, array $columnMappings): array
    {
        return $this->validator->inferMaxScores($rows, $columnMappings);
    }
}
