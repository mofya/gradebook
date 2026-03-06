<?php

namespace App\Filament\Resources\CourseOfferingResource\RelationManagers;

use App\Enums\ExamStatus;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class EnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('exam_score')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->step(0.01),
                Forms\Components\TextInput::make('ca_override')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->step(0.01),
                Forms\Components\TextInput::make('ca_override_reason')
                    ->maxLength(255),
                Forms\Components\TextInput::make('final_override')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->step(0.01),
                Forms\Components\TextInput::make('final_override_reason')
                    ->maxLength(255),
                Forms\Components\Select::make('status')
                    ->options([
                        'enrolled' => 'Enrolled',
                        'withdrawn' => 'Withdrawn',
                        'deferred' => 'Deferred',
                        'completed' => 'Completed',
                    ]),
                Forms\Components\Select::make('exam_status')
                    ->options(ExamStatus::class)
                    ->nullable(),
                Forms\Components\Textarea::make('remarks')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

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
                    ->label('CA Total')
                    ->numeric(2)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('exam_score')
                    ->label('Exam')
                    ->numeric(2)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('final_total')
                    ->label('Final Total')
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
                Actions\EditAction::make(),
                Actions\ViewAction::make(),
            ]);
    }
}
