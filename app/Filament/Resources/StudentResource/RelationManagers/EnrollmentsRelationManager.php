<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $title = 'Course Enrollments';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('courseOffering.course.code')
            ->columns([
                TextColumn::make('courseOffering.course.code')
                    ->label('Course Code')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('courseOffering.course.name')
                    ->label('Course Name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('courseOffering.semester.name')
                    ->label('Semester')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('study_mode')
                    ->label('Study Mode')
                    ->sortable(),
                TextColumn::make('final_grade')
                    ->label('Grade')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('courseOffering.course.code');
    }
}
