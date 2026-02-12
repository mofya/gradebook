<?php

namespace App\Filament\Resources\CourseResource\Pages;

use App\Filament\Resources\CourseResource;
use App\Models\Course;
use App\Models\Grade;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;

class EnterGrades extends Page
{
    protected static string $resource = CourseResource::class;

    protected string $view = 'filament.resources.course-resource.pages.enter-grades';

    public Course $course;

    public $assessment_id;

    public $grades = [];

    public function mount($record): void
    {
        $this->course = Course::findOrFail($record);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('assessment_id')
                    ->label('Assessment')
                    ->options($this->course->assessments->pluck('name', 'id'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn () => $this->loadGrades()),
            ]);
    }

    public function loadGrades(): void
    {
        if (! $this->assessment_id) {
            $this->grades = [];

            return;
        }

        $students = $this->course->students;
        $this->grades = $students->mapWithKeys(function ($student) {
            $grade = Grade::firstOrNew([
                'student_id' => $student->id,
                'course_id' => $this->course->id,
                'assessment_id' => $this->assessment_id,
            ]);

            return [$student->id => [
                'student_name' => $student->first_name.' '.$student->last_name,
                'grade' => $grade->grade,
            ]];
        })->toArray();
    }

    public function submit(): void
    {
        foreach ($this->grades as $student_id => $data) {
            Grade::updateOrCreate(
                [
                    'student_id' => $student_id,
                    'course_id' => $this->course->id,
                    'assessment_id' => $this->assessment_id,
                ],
                [
                    'grade' => $data['grade'],
                ]
            );
        }

        Notification::make()
            ->title('Grades saved successfully!')
            ->success()
            ->send();
    }
}
