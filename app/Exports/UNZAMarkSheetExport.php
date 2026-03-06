<?php

namespace App\Exports;

use App\Enums\ExamStatus;
use App\Models\CourseOffering;
use App\Services\GradingService;
use App\Services\ReportingService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UNZAMarkSheetExport implements FromArray, ShouldAutoSize, WithEvents, WithStyles, WithTitle
{
    /** @var array<int, array<int, mixed>> */
    protected array $rows = [];

    /** @var array<int, \App\Models\Assessment> */
    protected array $caAssessments = [];

    protected int $headerRowCount = 0;

    protected int $dataRowCount = 0;

    /** @var array<string, int> */
    protected array $gradeDistribution = [];

    protected int $passCount = 0;

    protected int $failCount = 0;

    public function __construct(
        protected CourseOffering $courseOffering,
    ) {
        $this->loadAndBuild();
    }

    public function title(): string
    {
        return 'UNZA Mark Sheet';
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
        $lastCol = $this->getLastColumnLetter();
        $dataStart = $this->headerRowCount + 1;
        $dataEnd = $this->headerRowCount + $this->dataRowCount;

        $styles = [];

        // Header section bold
        for ($i = 1; $i <= $this->headerRowCount; $i++) {
            $styles[$i] = ['font' => ['bold' => true]];
        }

        // Column header row
        $styles[$dataStart] = ['font' => ['bold' => true]];

        // Data area borders
        if ($this->dataRowCount > 0) {
            $styles["A{$dataStart}:{$lastCol}{$dataEnd}"] = [
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
            ];
        }

        return $styles;
    }

    /**
     * @return array<int, mixed>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                // Merge header cells
                $lastCol = $this->getLastColumnLetter();
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A1')->getFont()->setSize(14);

                // Freeze below the column header row
                $freezeRow = $this->headerRowCount + 2;
                $sheet->freezePane("A{$freezeRow}");

                // Number formatting for score columns
                $assessmentCount = count($this->caAssessments);
                $scoreColStart = 7 + $assessmentCount;
                $scoreColEnd = $scoreColStart + 2;
                $dataStart = $this->headerRowCount + 2;
                $dataEnd = $this->headerRowCount + $this->dataRowCount;

                for ($col = $scoreColStart; $col <= $scoreColEnd; $col++) {
                    $colLetter = $this->columnLetter($col);
                    $sheet->getStyle("{$colLetter}{$dataStart}:{$colLetter}{$dataEnd}")
                        ->getNumberFormat()
                        ->setFormatCode('0.00');
                }
            },
        ];
    }

    protected function loadAndBuild(): void
    {
        $this->courseOffering->load([
            'course',
            'semester.year',
            'lecturer',
            'assessmentGroups.assessments',
            'enrollments.student',
            'enrollments.gradeResults',
        ]);

        // Collect CA assessments
        foreach ($this->courseOffering->assessmentGroups as $group) {
            if ($group->type === 'ca') {
                foreach ($group->assessments as $assessment) {
                    $this->caAssessments[] = $assessment;
                }
            }
        }

        // Build header
        $this->buildHeader();

        // Build column headings
        $this->buildColumnHeadings();

        // Build data rows
        $this->buildDataRows();

        // Build footer
        $this->buildFooter();
    }

    protected function buildHeader(): void
    {
        $course = $this->courseOffering->course;
        $semester = $this->courseOffering->semester;
        $year = $semester->year ?? null;
        $lecturer = $this->courseOffering->lecturer;

        $enrollments = $this->courseOffering->enrollments;
        $studyModes = $enrollments->pluck('study_mode')->filter()->unique()->implode(', ') ?: 'REGULAR';
        $totalStudents = $enrollments->count();

        $this->rows[] = ['THE UNIVERSITY OF ZAMBIA'];
        $this->rows[] = ['Department: '.($course->department->name ?? 'N/A')];
        $this->rows[] = ['Semester: '.($semester->name ?? 'N/A').' | Academic Year: '.($year->name ?? 'N/A')];
        $this->rows[] = ['Course: '.$course->code.' - '.$course->name];
        $this->rows[] = ['Lecturer: '.($lecturer->name ?? 'N/A')];
        $this->rows[] = ['Study Mode: '.$studyModes.' | Total Students: '.$totalStudents];
        $this->rows[] = []; // Blank separator

        $this->headerRowCount = 7;
    }

    protected function buildColumnHeadings(): void
    {
        $caWeight = (int) $this->courseOffering->ca_weight;
        $examWeight = (int) $this->courseOffering->exam_weight;

        $headings = ['[n]', 'Student No', 'Check', 'Surname', 'Other Names', 'Sex'];

        foreach ($this->caAssessments as $assessment) {
            $maxRaw = (int) $assessment->max_raw_score;
            $headings[] = $assessment->name.' /'.$maxRaw;
        }

        $headings = array_merge($headings, [
            "CA({$caWeight})",
            "Exam({$examWeight})",
            'Total(100)',
            'Grade',
            'Def',
            'Sup',
            'Comment',
        ]);

        $this->rows[] = $headings;
        $this->headerRowCount++;
    }

    protected function buildDataRows(): void
    {
        $gradingService = app(GradingService::class);
        $reportingService = app(ReportingService::class);

        $enrollments = $this->courseOffering->enrollments;
        $sorted = $enrollments->sortBy(fn ($e) => strtoupper($e->student->last_name ?? '').' '.($e->student->first_name ?? ''));

        $this->gradeDistribution = [
            'A+' => 0, 'A' => 0, 'B+' => 0, 'B' => 0,
            'C+' => 0, 'C' => 0, 'D+' => 0, 'D' => 0, 'NE' => 0,
        ];

        $passGrades = ['A+', 'A', 'B+', 'B', 'C+', 'C'];
        $rowNum = 0;

        foreach ($sorted as $enrollment) {
            $rowNum++;
            $student = $enrollment->student;
            $gradeResults = $enrollment->gradeResults->keyBy('assessment_id');

            $row = [
                $rowNum,
                $student->student_id_number,
                $this->checkDigit($student->student_id_number),
                strtoupper($student->last_name ?? ''),
                ucfirst(strtolower($student->first_name ?? '')),
                $student->gender,
            ];

            foreach ($this->caAssessments as $assessment) {
                $result = $gradeResults->get($assessment->id);
                if ($result && $result->raw_score !== null && ! $result->is_excused) {
                    $row[] = round((float) $result->raw_score, 2);
                } else {
                    $row[] = null;
                }
            }

            $row[] = $enrollment->ca_total !== null ? round((float) $enrollment->ca_total, 2) : null;
            $row[] = $enrollment->exam_score !== null ? round((float) $enrollment->exam_score, 2) : null;
            $row[] = $enrollment->final_total !== null ? round((float) $enrollment->final_total, 2) : null;
            $row[] = $enrollment->final_grade;

            $row[] = $enrollment->exam_status === ExamStatus::Deferred ? 'DV' : null;
            $row[] = $enrollment->exam_status === ExamStatus::Supplementary ? 'SP' : null;

            $row[] = $enrollment->comment;

            $this->rows[] = $row;

            // Track distribution
            $grade = $enrollment->final_grade;
            if ($grade && isset($this->gradeDistribution[$grade])) {
                $this->gradeDistribution[$grade]++;
            }

            if ($grade && in_array($grade, $passGrades, true)) {
                $this->passCount++;
            } elseif ($grade && ! in_array($grade, ['NE', 'DV', 'EX', 'ABS', 'WH'], true)) {
                $this->failCount++;
            }
        }

        $this->dataRowCount = $rowNum + 1; // +1 for column heading row
    }

    protected function buildFooter(): void
    {
        $this->rows[] = []; // Blank separator
        $this->rows[] = ['GRADE DISTRIBUTION'];

        $distRow = [];
        $countRow = [];
        foreach ($this->gradeDistribution as $letter => $count) {
            $distRow[] = $letter;
            $countRow[] = $count;
        }
        $this->rows[] = $distRow;
        $this->rows[] = $countRow;

        $this->rows[] = [];
        $this->rows[] = ['Pass: '.$this->passCount, 'Fail: '.$this->failCount, 'Total: '.($this->passCount + $this->failCount)];
    }

    protected function checkDigit(?string $studentId): string
    {
        if ($studentId === null || strlen($studentId) < 3) {
            return '[-]';
        }

        return '['.substr($studentId, -3).']';
    }

    protected function getLastColumnLetter(): string
    {
        $totalCols = 6 + count($this->caAssessments) + 7; // base + assessments + CA/Exam/Total/Grade/Def/Sup/Comment

        return $this->columnLetter($totalCols);
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
