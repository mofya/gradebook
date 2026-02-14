<?php

namespace App\Filament\Pages;

use App\Models\CourseOffering;
use App\Services\ReportingService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class GenderAnalysis extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Gender Analysis';

    protected string $view = 'filament.pages.gender-analysis';

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
        $report = $reportingService->generateGenderAnalysis($courseOffering);

        $this->reportData = [
            'course_code' => $report['course_offering']->course->code,
            'course_name' => $report['course_offering']->course->name,
            'semester' => ($report['course_offering']->semester->year->name ?? '').' '.$report['course_offering']->semester->name,
            'analysis' => $report['analysis'],
        ];
    }
}
