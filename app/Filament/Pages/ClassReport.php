<?php

namespace App\Filament\Pages;

use App\Exports\GradeSheetExport;
use App\Models\CourseOffering;
use App\Services\ReportingService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClassReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Class Report';

    protected string $view = 'filament.pages.class-report';

    public ?int $course_offering_id = null;

    public ?array $reportData = null;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('course_offering_id')
                    ->label('Course Offering')
                    ->options(
                        CourseOffering::query()
                            ->with(['course', 'semester.year'])
                            ->get()
                            ->mapWithKeys(fn ($co) => [
                                $co->id => $co->course->code.' - '.($co->semester->year->name ?? '').' '.$co->semester->name,
                            ])
                    )
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn () => $this->generateReport()),
            ]);
    }

    public function generateReport(): void
    {
        if (! $this->course_offering_id) {
            $this->reportData = null;

            return;
        }

        $courseOffering = CourseOffering::find($this->course_offering_id);

        if (! $courseOffering) {
            $this->reportData = null;

            return;
        }

        $reportingService = app(ReportingService::class);
        $report = $reportingService->generateOfferingReport($courseOffering);

        $this->reportData = [
            'course_code' => $report['course_offering']->course->code,
            'course_name' => $report['course_offering']->course->name,
            'semester' => ($report['course_offering']->semester->year->name ?? '').' '.$report['course_offering']->semester->name,
            'lecturer' => $report['course_offering']->lecturer?->name ?? 'Unassigned',
            'stats' => $report['stats'],
            'distribution' => $report['distribution'],
            'assessment_stats' => $report['assessment_stats'] ?? [],
            'students' => $report['enrollments']->map(fn ($e) => [
                'name' => $e->student->first_name.' '.$e->student->last_name,
                'student_id' => $e->student->student_id_number ?? $e->student->email,
                'ca_total' => $e->ca_total,
                'exam_score' => $e->exam_score,
                'final_total' => $e->final_total,
                'final_grade' => $e->final_grade,
                'grade_points' => $e->grade_points,
                'status' => $e->status,
            ])->toArray(),
        ];
    }

    public function exportExcel(): BinaryFileResponse
    {
        $courseOffering = CourseOffering::findOrFail($this->course_offering_id);

        return Excel::download(
            new GradeSheetExport($courseOffering),
            'grade_sheet_'.$courseOffering->id.'_'.now()->format('Ymd').'.xlsx'
        );
    }
}
