<?php

namespace App\Filament\Resources\EnrollmentResource\Pages;

use App\Filament\Resources\EnrollmentResource;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Student;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListEnrollments extends ListRecords
{
    protected static string $resource = EnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('bulkEnroll')
                ->label('Bulk Enroll')
                ->icon('heroicon-o-user-group')
                ->color('success')
                ->modalHeading('Bulk Enroll Students')
                ->modalDescription('Select a course offering and the students you want to enroll.')
                ->modalSubmitActionLabel('Enroll Students')
                ->schema([
                    Select::make('course_offering_id')
                        ->label('Course Offering')
                        ->options(
                            CourseOffering::query()
                                ->with(['course', 'semester.year'])
                                ->get()
                                ->mapWithKeys(fn (CourseOffering $offering) => [
                                    $offering->id => $offering->course->code.' - '.$offering->course->name.' ('.$offering->semester->year->name.', '.$offering->semester->name.')',
                                ])
                        )
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (callable $set) => $set('student_ids', [])),
                    Select::make('student_ids')
                        ->label('Students')
                        ->getSearchResultsUsing(function (string $search, callable $get): array {
                            $courseOfferingId = $get('course_offering_id');

                            $query = Student::query()
                                ->where(function ($q) use ($search) {
                                    $q->where('first_name', 'ilike', "%{$search}%")
                                        ->orWhere('last_name', 'ilike', "%{$search}%")
                                        ->orWhere('email', 'ilike', "%{$search}%")
                                        ->orWhere('student_id_number', 'ilike', "%{$search}%");
                                })
                                ->orderBy('last_name');

                            if ($courseOfferingId) {
                                $query->whereNotIn('id', Enrollment::where('course_offering_id', $courseOfferingId)->pluck('student_id'));
                            }

                            return $query->limit(50)
                                ->get()
                                ->mapWithKeys(fn (Student $student) => [
                                    $student->id => $student->last_name.', '.$student->first_name.' ('.$student->email.')',
                                ])
                                ->all();
                        })
                        ->getOptionLabelsUsing(fn (array $values): array => Student::whereIn('id', $values)
                            ->get()
                            ->mapWithKeys(fn (Student $student) => [
                                $student->id => $student->last_name.', '.$student->first_name.' ('.$student->email.')',
                            ])
                            ->all()
                        )
                        ->multiple()
                        ->searchable()
                        ->required()
                        ->helperText('Search by name, email, or student ID. Only unenrolled students are shown.'),
                ])
                ->action(function (array $data): void {
                    $enrolled = DB::transaction(function () use ($data): int {
                        $count = 0;

                        foreach ($data['student_ids'] as $studentId) {
                            Enrollment::firstOrCreate(
                                [
                                    'student_id' => $studentId,
                                    'course_offering_id' => $data['course_offering_id'],
                                ],
                                [
                                    'source' => 'manual',
                                    'status' => 'active',
                                ]
                            );
                            $count++;
                        }

                        return $count;
                    });

                    Notification::make()
                        ->title("{$enrolled} students enrolled successfully.")
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
