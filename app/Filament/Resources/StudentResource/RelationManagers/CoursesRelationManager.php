<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CoursesRelationManager extends RelationManager
{
    protected static string $relationship = 'courses';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Course Name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('code')
                    ->label('Course Code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(100),
                Forms\Components\Select::make('year_id')
                    ->label('Academic Year')
                    ->relationship('year', 'name')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Course Name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Course Code')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('year.name')
                    ->label('Academic Year')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                // Add filters if necessary
            ])
            ->headerActions([
                Actions\CreateAction::make(),
                Actions\AttachAction::make(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DetachAction::make()
                    ->modalHeading('Detach Course')
                    ->modalDescription('Are you sure? This will remove the student from this course.'),
            ])
            ->bulkActions([
                Actions\DetachBulkAction::make()
                    ->modalHeading('Detach Selected Courses')
                    ->modalDescription('Are you sure? This will remove the student from the selected courses.'),
            ]);
    }
}
