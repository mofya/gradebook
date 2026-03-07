<?php

namespace App\Filament\Resources\GradingSchemeResource\RelationManagers;

use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LevelsRelationManager extends RelationManager
{
    protected static string $relationship = 'levels';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('letter')
                    ->required()
                    ->maxLength(5),
                Forms\Components\TextInput::make('min_mark')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->maxValue(100),
                Forms\Components\TextInput::make('max_mark')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->maxValue(100),
                Forms\Components\TextInput::make('grade_points')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->maxValue(4)
                    ->step(0.1),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('letter')
                    ->sortable(),
                Tables\Columns\TextColumn::make('min_mark')
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_mark')
                    ->sortable(),
                Tables\Columns\TextColumn::make('grade_points')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->modalHeading('Delete Grade Level')
                    ->modalDescription('Are you sure? This will remove this grade level from the scheme.'),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make()
                    ->modalHeading('Delete Selected Grade Levels')
                    ->modalDescription('Are you sure? This will remove the selected grade levels from the scheme.'),
            ]);
    }
}
