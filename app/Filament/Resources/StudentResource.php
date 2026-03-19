<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentResource\Pages;
use App\Filament\Resources\StudentResource\RelationManagers;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Student;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Students';

    protected static string|\UnitEnum|null $navigationGroup = 'Students & Grading';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'email';

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'email', 'student_id_number'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Name' => $record->first_name.' '.$record->last_name,
            'Student ID' => $record->student_id_number ?? '—',
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Personal Information')
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('last_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->required()
                            ->email()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('student_id_number')
                            ->unique(ignoreRecord: true)
                            ->nullable(),
                        Forms\Components\Select::make('gender')
                            ->options(['Male' => 'Male', 'Female' => 'Female'])
                            ->nullable(),
                    ])
                    ->columns(2),

                Section::make('Academic Details')
                    ->schema([
                        Forms\Components\TextInput::make('program')
                            ->nullable(),
                        Forms\Components\TextInput::make('year_of_study')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(7)
                            ->nullable(),
                        Forms\Components\Select::make('study_mode')
                            ->options([
                                'Full-time' => 'Full-time',
                                'Part-time' => 'Part-time',
                                'Distance' => 'Distance',
                            ])
                            ->nullable(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Personal Information')
                    ->icon(Heroicon::OutlinedUser)
                    ->schema([
                        TextEntry::make('first_name'),
                        TextEntry::make('last_name'),
                        TextEntry::make('email')
                            ->icon(Heroicon::OutlinedEnvelope),
                        TextEntry::make('student_id_number')
                            ->label('Student ID')
                            ->placeholder('Not provided'),
                        TextEntry::make('gender')
                            ->placeholder('Not specified'),
                    ])
                    ->columns(2),

                Section::make('Academic Details')
                    ->icon(Heroicon::OutlinedAcademicCap)
                    ->schema([
                        TextEntry::make('program')
                            ->placeholder('Not specified'),
                        TextEntry::make('year_of_study')
                            ->placeholder('Not specified'),
                        TextEntry::make('study_mode')
                            ->badge()
                            ->placeholder('Not specified'),
                        TextEntry::make('created_at')
                            ->label('Registered')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('first_name')
                    ->label('First Name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->label('Last Name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('student_id_number')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('program')
                    ->sortable(),
                Tables\Columns\TextColumn::make('year_of_study')
                    ->sortable(),
                Tables\Columns\TextColumn::make('study_mode')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered At')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('last_name', 'asc')
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->filters([
                SelectFilter::make('gender')
                    ->options([
                        'Male' => 'Male',
                        'Female' => 'Female',
                    ]),
                SelectFilter::make('year_of_study')
                    ->options([
                        '1' => 'Year 1',
                        '2' => 'Year 2',
                        '3' => 'Year 3',
                        '4' => 'Year 4',
                        '5' => 'Year 5',
                        '6' => 'Year 6',
                        '7' => 'Year 7',
                    ]),
                SelectFilter::make('study_mode')
                    ->options([
                        'Full-time' => 'Full-time',
                        'Part-time' => 'Part-time',
                        'Distance' => 'Distance',
                    ]),
                SelectFilter::make('program')
                    ->searchable()
                    ->preload()
                    ->options(fn (): array => Student::query()
                        ->whereNotNull('program')
                        ->distinct()
                        ->orderBy('program')
                        ->pluck('program', 'program')
                        ->all()),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->modalHeading('Delete Student')
                    ->modalDescription('Are you sure? This will remove the student and all their enrollment records.'),
            ])
            ->bulkActions([
                Actions\BulkAction::make('enroll')
                    ->label('Enroll in Course')
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->modalHeading('Enroll Selected Students')
                    ->modalDescription('Choose the course offering to enroll the selected students into.')
                    ->modalSubmitActionLabel('Enroll')
                    ->form([
                        Forms\Components\Select::make('course_offering_id')
                            ->label('Course Offering')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search): array {
                                return CourseOffering::query()
                                    ->with(['course', 'semester.year'])
                                    ->whereHas('course', fn ($q) => $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                                    ->orWhereHas('semester', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (CourseOffering $offering) => [
                                        $offering->id => $offering->course->code.' - '.$offering->course->name.' ('.$offering->semester->year->name.', '.$offering->semester->name.')',
                                    ])
                                    ->all();
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                $offering = CourseOffering::with(['course', 'semester.year'])->find($value);

                                return $offering
                                    ? $offering->course->code.' - '.$offering->course->name.' ('.$offering->semester->year->name.', '.$offering->semester->name.')'
                                    : null;
                            })
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $enrolled = 0;
                        $skipped = 0;

                        DB::transaction(function () use ($records, $data, &$enrolled, &$skipped): void {
                            foreach ($records as $student) {
                                $created = Enrollment::firstOrCreate(
                                    [
                                        'student_id' => $student->id,
                                        'course_offering_id' => $data['course_offering_id'],
                                    ],
                                    [
                                        'source' => 'manual',
                                        'status' => 'active',
                                    ]
                                );

                                if ($created->wasRecentlyCreated) {
                                    $enrolled++;
                                } else {
                                    $skipped++;
                                }
                            }
                        });

                        $offering = CourseOffering::with(['course', 'semester.year'])->find($data['course_offering_id']);
                        $offeringLabel = $offering
                            ? $offering->course->code.' - '.$offering->course->name
                            : '';

                        $message = "{$enrolled} students enrolled.";
                        if ($skipped > 0) {
                            $message .= " {$skipped} already enrolled (skipped).";
                        }

                        Notification::make()
                            ->title($message)
                            ->success()
                            ->send();

                        Notification::make()
                            ->title('Bulk Enrollment Complete')
                            ->icon('heroicon-o-academic-cap')
                            ->body("{$message} Course: {$offeringLabel}")
                            ->success()
                            ->sendToDatabase(auth()->user());
                    })
                    ->deselectRecordsAfterCompletion(),
                Actions\DeleteBulkAction::make()
                    ->modalHeading('Delete Selected Students')
                    ->modalDescription('Are you sure? This will remove the selected students and all their enrollment records.'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CoursesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudents::route('/'),
            'create' => Pages\CreateStudent::route('/create'),
            'view' => Pages\ViewStudent::route('/{record}'),
            'edit' => Pages\EditStudent::route('/{record}/edit'),
        ];
    }
}
