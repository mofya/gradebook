<?php

namespace App\Filament\Resources;

use App\Enums\ExamStatus;
use App\Filament\Resources\EnrollmentResource\Pages;
use App\Models\Enrollment;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EnrollmentResource extends Resource
{
    protected static ?string $model = Enrollment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static string|\UnitEnum|null $navigationGroup = 'Students & Grading';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Enrollment')
                    ->schema([
                        Forms\Components\Select::make('student_id')
                            ->relationship('student', 'email')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('course_offering_id')
                            ->relationship('courseOffering')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->course->code.' - '.$record->semester->name)
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('source')
                            ->options(['manual' => 'Manual', 'csv_import' => 'CSV Import', 'lms_sync' => 'LMS Sync']),
                        Forms\Components\Select::make('status')
                            ->options(['enrolled' => 'Enrolled', 'withdrawn' => 'Withdrawn', 'deferred' => 'Deferred', 'completed' => 'Completed']),
                        Forms\Components\Select::make('exam_status')
                            ->options(ExamStatus::class)
                            ->nullable(),
                        Forms\Components\Select::make('study_mode')
                            ->options([
                                'REGULAR' => 'Regular',
                                'PARALLEL' => 'Parallel',
                                'DISTANCE' => 'Distance',
                                'OTHER' => 'Other',
                            ])
                            ->default('REGULAR'),
                    ])
                    ->columns(2),

                Section::make('Scores')
                    ->schema([
                        Forms\Components\TextInput::make('ca_total')
                            ->numeric()
                            ->disabled(),
                        Forms\Components\TextInput::make('exam_score')
                            ->numeric()
                            ->nullable(),
                        Forms\Components\TextInput::make('final_total')
                            ->numeric()
                            ->disabled(),
                        Forms\Components\TextInput::make('final_grade')
                            ->disabled(),
                    ])
                    ->columns(4),

                Section::make('Overrides')
                    ->description('Override computed scores when manual adjustments are needed.')
                    ->schema([
                        Forms\Components\TextInput::make('ca_override')
                            ->numeric()
                            ->nullable()
                            ->live(),
                        Forms\Components\Textarea::make('ca_override_reason')
                            ->label('CA Override Reason')
                            ->placeholder('Mandatory when overriding CA total')
                            ->visible(fn (callable $get): bool => $get('ca_override') !== null && $get('ca_override') !== '')
                            ->requiredWith('ca_override')
                            ->rows(2),
                        Forms\Components\TextInput::make('final_override')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->label('Final Override')
                            ->live(),
                        Forms\Components\Textarea::make('final_override_reason')
                            ->label('Final Override Reason')
                            ->placeholder('Mandatory when overriding final total')
                            ->visible(fn (callable $get): bool => $get('final_override') !== null && $get('final_override') !== '')
                            ->requiredWith('final_override')
                            ->rows(2),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Comment')
                    ->schema([
                        Forms\Components\Textarea::make('comment')
                            ->label('Comment')
                            ->placeholder('Optional comment for the mark sheet (e.g. RPT, SUPP, etc.)')
                            ->rows(2)
                            ->nullable(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['student', 'courseOffering.course', 'courseOffering.semester']))
            ->columns([
                Tables\Columns\TextColumn::make('student.email')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('courseOffering.course.code')
                    ->label('Course')
                    ->sortable(),
                Tables\Columns\TextColumn::make('courseOffering.semester.name')
                    ->label('Semester')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'enrolled' => 'info',
                        'completed' => 'success',
                        'withdrawn' => 'danger',
                        'deferred' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('study_mode')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'REGULAR' => 'info',
                        'PARALLEL' => 'warning',
                        'DISTANCE' => 'success',
                        default => 'gray',
                    })
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('exam_status')
                    ->badge()
                    ->color(fn (?ExamStatus $state): string => match ($state) {
                        ExamStatus::NotEntered => 'gray',
                        ExamStatus::Supplementary => 'warning',
                        ExamStatus::Deferred => 'info',
                        ExamStatus::Exempt => 'success',
                        ExamStatus::Absent => 'danger',
                        ExamStatus::Withheld => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('ca_total')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('exam_score')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('final_total'),
                Tables\Columns\TextColumn::make('final_override')
                    ->label('Override')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('final_grade'),
                Tables\Columns\TextColumn::make('comment')
                    ->limit(30)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->defaultSort('student.email')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'enrolled' => 'Enrolled',
                        'withdrawn' => 'Withdrawn',
                        'deferred' => 'Deferred',
                        'completed' => 'Completed',
                    ]),
                SelectFilter::make('exam_status')
                    ->options(ExamStatus::class),
                SelectFilter::make('course_offering_id')
                    ->relationship('courseOffering', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->course->code.' - '.$record->semester->name)
                    ->label('Course Offering')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->modalHeading('Delete Enrollment')
                    ->modalDescription('Are you sure? This will remove the enrollment and associated grade data.'),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make()
                    ->modalHeading('Delete Selected Enrollments')
                    ->modalDescription('Are you sure? This will remove the selected enrollments and associated grade data.'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEnrollments::route('/'),
            'create' => Pages\CreateEnrollment::route('/create'),
            'edit' => Pages\EditEnrollment::route('/{record}/edit'),
        ];
    }
}
