<?php

namespace App\Filament\Resources\CourseResource\RelationManagers;

use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AssessmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assessments';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Assessment Name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('weight')
                    ->label('Weight (%)')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Assessment Name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('weight')->label('Weight (%)')->sortable(),
            ])
            ->filters([
                // Add filters if necessary
            ])
            ->headerActions([
                Actions\CreateAction::make(),
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
}
