<?php

namespace App\Exports;

use App\Exports\Sheets\ChartsSheet;
use App\Exports\Sheets\GenderAnalysisSheet;
use App\Exports\Sheets\GradeSummarySheet;
use App\Exports\Sheets\MarkSheet;
use App\Models\CourseOffering;
use App\Services\ReportingService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class GradeSheetExport implements WithMultipleSheets
{
    /** @var array<string, mixed> */
    protected array $reportData;

    protected Collection $enrollments;

    /** @var array<int, \App\Models\Assessment> */
    protected array $caAssessments;

    public function __construct(
        protected CourseOffering $courseOffering,
    ) {
        $this->loadData();
    }

    /**
     * @return array<int, mixed>
     */
    public function sheets(): array
    {
        $genderAnalysisSheet = new GenderAnalysisSheet($this->enrollments);

        return [
            new MarkSheet($this->courseOffering, $this->enrollments, $this->caAssessments),
            new GradeSummarySheet($this->courseOffering, $this->reportData),
            $genderAnalysisSheet,
            new ChartsSheet(
                $genderAnalysisSheet->getGenderGradeCounts(),
                $this->reportData['distribution'],
            ),
        ];
    }

    protected function loadData(): void
    {
        $reportingService = app(ReportingService::class);
        $this->reportData = $reportingService->generateOfferingReport($this->courseOffering);

        $this->courseOffering->load([
            'course',
            'semester.year',
            'lecturer',
            'assessmentGroups.assessments',
            'enrollments.student',
            'enrollments.gradeResults',
        ]);

        $this->enrollments = $this->courseOffering->enrollments;

        $this->caAssessments = [];
        foreach ($this->courseOffering->assessmentGroups as $group) {
            if ($group->type === 'ca') {
                foreach ($group->assessments as $assessment) {
                    $this->caAssessments[] = $assessment;
                }
            }
        }
    }
}
