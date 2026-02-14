<?php

namespace App\Filament\Resources\CourseResource\RelationManagers;

use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $title = 'Students';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student.student_id_number')
                    ->label('Student ID')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('student.last_name')
                    ->label('Last Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('student.first_name')
                    ->label('First Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('courseOffering.semester.name')
                    ->label('Semester')
                    ->sortable(),
                Tables\Columns\TextColumn::make('courseOffering.section')
                    ->label('Section')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'enrolled' => 'info',
                        'completed' => 'success',
                        'withdrawn' => 'danger',
                        'deferred' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('ca_total')
                    ->label('CA')
                    ->numeric(2)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('exam_score')
                    ->label('Exam')
                    ->numeric(2)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('final_total')
                    ->label('Total')
                    ->numeric(2)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('final_grade')
                    ->label('Grade')
                    ->badge()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('grade_points')
                    ->label('GP')
                    ->numeric(1)
                    ->placeholder('—'),
            ])
            ->defaultSort('student.last_name')
            ->actions([
                Actions\ViewAction::make(),
            ]);
    }
}
