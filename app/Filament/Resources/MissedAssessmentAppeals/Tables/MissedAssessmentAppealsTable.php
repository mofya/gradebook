<?php

namespace App\Filament\Resources\MissedAssessmentAppeals\Tables;

use App\Models\MissedAssessmentAppeal;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MissedAssessmentAppealsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                TextColumn::make('courseOffering.course.code')
                    ->label('Course')
                    ->searchable(),
                TextColumn::make('student.student_id_number')
                    ->label('Student ID')
                    ->searchable(),
                TextColumn::make('student.first_name')
                    ->label('Name')
                    ->state(fn ($record): string => trim(($record->student->first_name ?? '').' '.($record->student->last_name ?? '')))
                    ->searchable(['student.first_name', 'student.last_name']),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->alignCenter(),
                IconColumn::make('dean_confirmed')
                    ->label('Dean')
                    ->boolean(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => MissedAssessmentAppeal::STATUS_PENDING,
                        'info' => MissedAssessmentAppeal::STATUS_UNDER_REVIEW,
                        'success' => MissedAssessmentAppeal::STATUS_APPROVED,
                        'danger' => MissedAssessmentAppeal::STATUS_REJECTED,
                        'primary' => MissedAssessmentAppeal::STATUS_PARTIALLY_APPROVED,
                    ]),
                TextColumn::make('submitted_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                TextColumn::make('reviewed_at')
                    ->dateTime('M d, Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        MissedAssessmentAppeal::STATUS_PENDING => 'Pending',
                        MissedAssessmentAppeal::STATUS_UNDER_REVIEW => 'Under review',
                        MissedAssessmentAppeal::STATUS_APPROVED => 'Approved',
                        MissedAssessmentAppeal::STATUS_REJECTED => 'Rejected',
                        MissedAssessmentAppeal::STATUS_PARTIALLY_APPROVED => 'Partially approved',
                    ]),
                SelectFilter::make('course_offering_id')
                    ->label('Course offering')
                    ->relationship('courseOffering.course', 'code'),
            ])
            ->recordActions([
                EditAction::make()->label('Review'),
            ]);
    }
}
