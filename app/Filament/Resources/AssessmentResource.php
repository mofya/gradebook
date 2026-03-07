<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssessmentResource\Pages;
use App\Models\Assessment;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AssessmentResource extends Resource
{
    protected static ?string $model = Assessment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard';

    protected static ?string $navigationLabel = 'Assessments';

    protected static string|\UnitEnum|null $navigationGroup = 'Course Management';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Assessment Details')
                    ->schema([
                        Forms\Components\Select::make('course_id')
                            ->label('Course')
                            ->relationship('course', 'name')
                            ->required(),
                        Forms\Components\TextInput::make('name')
                            ->label('Assessment Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('assessment_group_id')
                            ->relationship('assessmentGroup', 'name')
                            ->nullable()
                            ->searchable()
                            ->preload(),
                        Forms\Components\DatePicker::make('due_date')
                            ->nullable(),
                    ])
                    ->columns(2),

                Section::make('Scoring')
                    ->schema([
                        Forms\Components\TextInput::make('weight')
                            ->label('Weight (%)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%'),
                        Forms\Components\TextInput::make('max_raw_score')
                            ->numeric()
                            ->default(100),
                        Forms\Components\TextInput::make('normalized_to')
                            ->numeric()
                            ->nullable(),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(4),

                Section::make('Options')
                    ->schema([
                        Forms\Components\Toggle::make('has_subsections'),
                        Forms\Components\Toggle::make('is_published'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('course.name')
                    ->label('Course')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Assessment Name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('weight')
                    ->label('Weight (%)')
                    ->sortable(),
                Tables\Columns\TextColumn::make('assessmentGroup.name')
                    ->label('Group'),
                Tables\Columns\TextColumn::make('max_raw_score'),
                Tables\Columns\IconColumn::make('is_published')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->filters([
                TernaryFilter::make('is_published'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->modalHeading('Delete Assessment')
                    ->modalDescription('Are you sure? This will remove the assessment and all associated grades.'),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make()
                    ->modalHeading('Delete Selected Assessments')
                    ->modalDescription('Are you sure? This will remove the selected assessments and all associated grades.'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Define any relation managers if needed
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssessments::route('/'),
            'create' => Pages\CreateAssessment::route('/create'),
            'edit' => Pages\EditAssessment::route('/{record}/edit'),
        ];
    }
}
