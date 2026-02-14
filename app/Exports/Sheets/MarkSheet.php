<?php

namespace App\Exports\Sheets;

use App\Enums\ExamStatus;
use App\Models\CourseOffering;
use App\Services\GradingService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MarkSheet implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    /** @var array<int, array<string, mixed>> */
    protected array $rows = [];

    /** @var array<int, \App\Models\Assessment> */
    protected array $caAssessments;

    public function __construct(
        protected CourseOffering $courseOffering,
        protected Collection $enrollments,
        array $caAssessments,
    ) {
        $this->caAssessments = $caAssessments;
        $this->buildRows();
    }

    public function title(): string
    {
        return 'Mark Sheet';
    }

    public function headings(): array
    {
        $headings = ['#', 'Student ID', 'Name', 'Gender', 'Programme', 'Category'];

        foreach ($this->caAssessments as $assessment) {
            $maxRaw = (float) $assessment->max_raw_score;
            $headings[] = $assessment->name.' /'.(int) $maxRaw;
        }

        return array_merge($headings, ['CA/100', 'Exam/100', 'Final Mark/100', 'Grade', 'Exam Grade', 'Def', 'Sup', 'GP', 'Comment', 'Check Digit']);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return $this->rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = count($this->rows) + 1;
        $lastCol = count($this->headings());
        $lastColLetter = $this->columnLetter($lastCol);

        return [
            1 => ['font' => ['bold' => true]],
            "A1:{$lastColLetter}{$lastRow}" => [
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
            ],
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');

                $assessmentCount = count($this->caAssessments);
                $scoreColStart = 7 + $assessmentCount;
                $scoreColEnd = $scoreColStart + 2;

                for ($col = 7; $col <= $scoreColEnd; $col++) {
                    $colLetter = $this->columnLetter($col);
                    $lastRow = count($this->rows) + 1;
                    $sheet->getStyle("{$colLetter}2:{$colLetter}{$lastRow}")
                        ->getNumberFormat()
                        ->setFormatCode('0.00');
                }
            },
        ];
    }

    protected function buildRows(): void
    {
        $gradingService = app(GradingService::class);
        $sorted = $this->enrollments->sortBy(fn ($e) => $e->student->student_id_number ?? '');

        $rowNum = 0;
        foreach ($sorted as $enrollment) {
            $rowNum++;
            $student = $enrollment->student;
            $gradeResults = $enrollment->gradeResults->keyBy('assessment_id');

            $name = strtoupper($student->last_name).', '.ucfirst(strtolower($student->first_name));

            $row = [
                $rowNum,
                $student->student_id_number,
                $name,
                $student->gender,
                $student->program,
                $student->study_mode ?? 'FT',
            ];

            foreach ($this->caAssessments as $assessment) {
                $result = $gradeResults->get($assessment->id);
                if ($result && $result->raw_score !== null && ! $result->is_excused) {
                    $maxRaw = (float) $assessment->max_raw_score;
                    $normalized = $maxRaw > 0
                        ? round(((float) $result->raw_score / $maxRaw) * 100, 2)
                        : 0;
                    $row[] = $normalized;
                } else {
                    $row[] = null;
                }
            }

            $row[] = $enrollment->ca_total !== null ? round((float) $enrollment->ca_total, 2) : null;
            $row[] = $enrollment->exam_score !== null ? round((float) $enrollment->exam_score, 2) : null;
            $row[] = $enrollment->final_total !== null ? round((float) $enrollment->final_total, 2) : null;
            $row[] = $enrollment->final_grade;

            // Exam Grade: letter grade for just the exam score
            $examGrade = $enrollment->exam_score !== null
                ? $gradingService->getLetterGrade((float) $enrollment->exam_score)
                : null;
            $row[] = $examGrade;

            // Deferred / Supplementary flags
            $row[] = $enrollment->exam_status === ExamStatus::Deferred ? 'DV' : null;
            $row[] = $enrollment->exam_status === ExamStatus::Supplementary ? 'SP' : null;

            $row[] = $enrollment->grade_points;
            $row[] = $enrollment->comment;
            $row[] = $this->checkDigit($student->student_id_number);

            $this->rows[] = $row;
        }
    }

    protected function checkDigit(?string $studentId): string
    {
        if ($studentId === null || strlen($studentId) < 3) {
            return '[-]';
        }

        return '['.substr($studentId, -3).']';
    }

    protected function columnLetter(int $columnNumber): string
    {
        $letter = '';
        while ($columnNumber > 0) {
            $columnNumber--;
            $letter = chr(65 + ($columnNumber % 26)).$letter;
            $columnNumber = intdiv($columnNumber, 26);
        }

        return $letter;
    }
}
