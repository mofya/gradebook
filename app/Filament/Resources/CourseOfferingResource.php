<?php

namespace App\Filament\Resources;

use App\Enums\OfferingStatus;
use App\Filament\Resources\CourseOfferingResource\Pages;
use App\Filament\Resources\CourseOfferingResource\RelationManagers;
use App\Models\CourseOffering;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CourseOfferingResource extends Resource
{
    protected static ?string $model = CourseOffering::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Course Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'course.code';

    public static function getGloballySearchableAttributes(): array
    {
        return ['course.code', 'course.name', 'semester.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Course' => $record->course?->name ?? '—',
            'Semester' => $record->semester?->name ?? '—',
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'active')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Active course offerings';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Course & Semester')
                    ->schema([
                        Forms\Components\Select::make('course_id')
                            ->relationship('course', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('semester_id')
                            ->relationship('semester', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('section')
                            ->maxLength(20)
                            ->placeholder('e.g. A, B, Evening'),
                        Forms\Components\Select::make('lecturer_id')
                            ->relationship('lecturer', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ])
                    ->columns(2),

                Section::make('Grading Configuration')
                    ->schema([
                        Forms\Components\Select::make('grading_scheme_id')
                            ->relationship('gradingScheme', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Use default scheme'),
                        Forms\Components\TextInput::make('ca_weight')
                            ->numeric()
                            ->required()
                            ->default(50)
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                if ($state !== null && $state !== '') {
                                    $set('exam_weight', round(100 - (float) $state, 2));
                                }
                            })
                            ->rules([
                                fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $sum = bcadd((string) ($value ?? 0), (string) ($get('exam_weight') ?? 0), 2);
                                    if ($sum !== '100.00') {
                                        $fail('CA weight and exam weight must sum to 100.');
                                    }
                                },
                            ]),
                        Forms\Components\TextInput::make('exam_weight')
                            ->numeric()
                            ->required()
                            ->default(50)
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                if ($state !== null && $state !== '') {
                                    $set('ca_weight', round(100 - (float) $state, 2));
                                }
                            }),
                    ])
                    ->columns(3),

                Section::make('Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(OfferingStatus::class)
                            ->default('draft'),
                        Forms\Components\Toggle::make('is_published'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Course & Semester')
                    ->icon('heroicon-o-academic-cap')
                    ->schema([
                        TextEntry::make('course.name')
                            ->label('Course'),
                        TextEntry::make('course.code')
                            ->label('Course Code'),
                        TextEntry::make('semester.name')
                            ->label('Semester'),
                        TextEntry::make('section')
                            ->placeholder('No section'),
                        TextEntry::make('lecturer.name')
                            ->label('Lecturer')
                            ->placeholder('Unassigned'),
                    ])
                    ->columns(2),

                Section::make('Grading Configuration')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        TextEntry::make('gradingScheme.name')
                            ->label('Grading Scheme')
                            ->placeholder('Default'),
                        TextEntry::make('ca_weight')
                            ->suffix('%'),
                        TextEntry::make('exam_weight')
                            ->suffix('%'),
                    ])
                    ->columns(3),

                Section::make('Status')
                    ->icon('heroicon-o-signal')
                    ->schema([
                        TextEntry::make('status')
                            ->badge(),
                        IconEntry::make('is_published')
                            ->boolean(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['course', 'semester', 'lecturer', 'gradingScheme']))
            ->columns([
                Tables\Columns\TextColumn::make('course.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('course.code')
                    ->label('Code')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('semester.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('section')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('lecturer.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('gradingScheme.name')
                    ->label('Grading Scheme')
                    ->placeholder('Default')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('ca_weight')
                    ->suffix('%')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('exam_weight')
                    ->suffix('%')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_published')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(OfferingStatus::class),
                SelectFilter::make('semester')
                    ->relationship('semester', 'name'),
                TernaryFilter::make('is_published'),
            ])
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->modalHeading('Delete Course Offering')
                    ->modalDescription('Are you sure? This will remove the offering, its enrollments, and all grade data.'),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make()
                    ->modalHeading('Delete Selected Course Offerings')
                    ->modalDescription('Are you sure? This will remove the selected offerings, their enrollments, and all grade data.'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AssessmentGroupsRelationManager::class,
            RelationManagers\EnrollmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourseOfferings::route('/'),
            'create' => Pages\CreateCourseOffering::route('/create'),
            'view' => Pages\ViewCourseOffering::route('/{record}'),
            'edit' => Pages\EditCourseOffering::route('/{record}/edit'),
            'weight-breakdown' => Pages\WeightBreakdown::route('/{record}/weight-breakdown'),
            'weight-overview' => Pages\WeightOverview::route('/{record}/weight-overview'),
            'enter-exam-grades' => Pages\EnterExamGrades::route('/{record}/enter-exam-grades'),
            'import-grades' => Pages\ImportGrades::route('/{record}/import-grades'),
            'class-gradebook' => Pages\ClassGradebook::route('/{record}/class-gradebook'),
        ];
    }
}
