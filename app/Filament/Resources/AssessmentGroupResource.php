<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssessmentGroupResource\Pages;
use App\Models\AssessmentGroup;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AssessmentGroupResource extends Resource
{
    protected static ?string $model = AssessmentGroup::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static string|\UnitEnum|null $navigationGroup = 'Course Management';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Group Details')
                    ->schema([
                        Forms\Components\Select::make('course_offering_id')
                            ->relationship('courseOffering')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->course->code.' - '.$record->semester->name)
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('type')
                            ->options(['ca' => 'Continuous Assessment', 'exam' => 'Examination'])
                            ->required(),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),

                Section::make('Weighting')
                    ->schema([
                        Forms\Components\Select::make('weight_mode')
                            ->options([
                                'percentage' => 'Percentage',
                                'points' => 'Points',
                            ])
                            ->required()
                            ->default('percentage')
                            ->live(),
                        Forms\Components\TextInput::make('weight_percentage')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->visible(fn (callable $get): bool => $get('weight_mode') === 'percentage')
                            ->requiredIf('weight_mode', 'percentage'),
                        Forms\Components\TextInput::make('weight_points')
                            ->numeric()
                            ->minValue(0)
                            ->visible(fn (callable $get): bool => $get('weight_mode') === 'points')
                            ->requiredIf('weight_mode', 'points'),
                    ])
                    ->columns(3),

                Section::make('Aggregation')
                    ->description('Controls how individual assessment scores within this group are combined.')
                    ->schema([
                        Forms\Components\Select::make('aggregation_mode')
                            ->options([
                                'WEIGHTED_AVERAGE' => 'Weighted Average (default)',
                                'MAX' => 'Maximum Score',
                                'DROP_LOWEST' => 'Drop Lowest',
                            ])
                            ->default('WEIGHTED_AVERAGE')
                            ->required()
                            ->live()
                            ->helperText('Weighted Average: sum of normalized scores. Max: take the highest. Drop Lowest: drop N lowest, then average.'),
                        Forms\Components\TextInput::make('drop_count')
                            ->label('Number to Drop')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->visible(fn (callable $get): bool => $get('aggregation_mode') === 'DROP_LOWEST')
                            ->requiredIf('aggregation_mode', 'DROP_LOWEST')
                            ->helperText('How many of the lowest scores to drop before averaging.'),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('courseOffering.course.code')
                    ->label('Course')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ca' => 'info',
                        'exam' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('weight_mode')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'percentage' => 'info',
                        'points' => 'warning',
                        default => 'gray',
                    })
                    ->label('Mode'),
                Tables\Columns\TextColumn::make('weight_percentage')
                    ->suffix('%')
                    ->label('Weight %')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('weight_points')
                    ->label('Weight Pts')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sort_order')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'ca' => 'CA',
                        'exam' => 'Exam',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->modalHeading('Delete Assessment Group')
                    ->modalDescription('Are you sure? This will remove the group and its assessments.'),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make()
                    ->modalHeading('Delete Selected Assessment Groups')
                    ->modalDescription('Are you sure? This will remove the selected groups and their assessments.'),
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
            'index' => Pages\ListAssessmentGroups::route('/'),
            'create' => Pages\CreateAssessmentGroup::route('/create'),
            'edit' => Pages\EditAssessmentGroup::route('/{record}/edit'),
        ];
    }
}
