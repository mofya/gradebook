<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CourseResource\Pages;
use App\Filament\Resources\CourseResource\RelationManagers;
use App\Models\Course;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Academic Setup';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Course Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100),
                        Select::make('year_id')
                            ->relationship('year', 'name')
                            ->required(),
                        Select::make('dept_id')
                            ->relationship('department', 'dept_name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('credits')
                            ->numeric()
                            ->required()
                            ->default(3),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('code')->sortable()->searchable(),
                TextColumn::make('year.name')->label('Year')->sortable(),
                TextColumn::make('department.dept_name')->label('Department')->sortable(),
                TextColumn::make('credits')->sortable(),
            ])
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->filters([
                SelectFilter::make('year_id')
                    ->relationship('year', 'name')
                    ->label('Year'),
                SelectFilter::make('dept_id')
                    ->relationship('department', 'dept_name')
                    ->label('Department'),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->modalHeading('Delete Selected Courses')
                        ->modalDescription('Are you sure? This will remove the selected courses and all associated offerings.'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StudentsRelationManager::class,
            RelationManagers\AssessmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourses::route('/'),
            'create' => Pages\CreateCourse::route('/create'),
            'edit' => Pages\EditCourse::route('/{record}/edit'),
            'enter-grades' => Pages\EnterGrades::route('/{record}/enter-grades'),
            'assessment-weights' => Pages\AssessmentWeights::route('/{record}/assessment-weights'),
        ];
    }
}
