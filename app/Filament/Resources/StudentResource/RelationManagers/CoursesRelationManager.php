<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use App\Models\Course;
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
                Tables\Actions\CreateAction::make(), // Allow creating new courses
                Tables\Actions\AttachAction::make(), // Allow attaching existing courses
            ])
            ->actions([
                Tables\Actions\EditAction::make(),    // Allow editing course details
                Tables\Actions\DetachAction::make(),  // Allow detaching courses from the student
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make(),
            ]);
    }
}
