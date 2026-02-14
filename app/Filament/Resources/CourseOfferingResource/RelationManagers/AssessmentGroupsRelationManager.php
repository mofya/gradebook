<?php

namespace App\Filament\Resources\CourseOfferingResource\RelationManagers;

use App\Services\GradingService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AssessmentGroupsRelationManager extends RelationManager
{
    protected static string $relationship = 'assessmentGroups';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('type')
                    ->options([
                        'ca' => 'Continuous Assessment',
                        'exam' => 'Exam',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
                Forms\Components\Select::make('weight_mode')
                    ->options([
                        'percentage' => 'Percentage',
                        'points' => 'Points',
                    ])
                    ->default('percentage')
                    ->live(),
                Forms\Components\TextInput::make('weight_percentage')
                    ->numeric()
                    ->suffix('%')
                    ->minValue(0)
                    ->maxValue(100)
                    ->visible(fn (callable $get): bool => $get('weight_mode') !== 'points'),
                Forms\Components\TextInput::make('weight_points')
                    ->numeric()
                    ->minValue(0)
                    ->visible(fn (callable $get): bool => $get('weight_mode') === 'points'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ca' => 'info',
                        'exam' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('weight_mode')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('weight_percentage')
                    ->suffix('%')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('weight_points')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('assessments_count')
                    ->counts('assessments')
                    ->label('Assessments'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                Actions\CreateAction::make(),
                Actions\Action::make('recalculate_grades')
                    ->label('Recalculate All Grades')
                    ->icon('heroicon-o-calculator')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('This will recalculate CA totals, exam totals, final marks, letter grades, and grade points for all enrolled students.')
                    ->action(function () {
                        $gradingService = app(GradingService::class);
                        $count = $gradingService->resolveAllGrades($this->getOwnerRecord());

                        Notification::make()
                            ->title("Recalculated grades for {$count} students.")
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Actions\ViewAction::make()
                    ->modalContent(fn (Model $record) => view(
                        'filament.resources.course-offering-resource.relation-managers.view-assessment-group',
                        [
                            'assessments' => $record->assessments,
                            'group' => $record,
                            'offering' => $this->getOwnerRecord(),
                        ]
                    ))
                    ->modalHeading(fn (Model $record) => $record->name.' — Assessments'),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ]);
    }
}
