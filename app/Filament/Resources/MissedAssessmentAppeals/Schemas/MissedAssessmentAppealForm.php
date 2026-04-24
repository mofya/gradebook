<?php

namespace App\Filament\Resources\MissedAssessmentAppeals\Schemas;

use App\Models\MissedAssessmentAppeal;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class MissedAssessmentAppealForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('context')
                    ->label('Student & course')
                    ->content(fn ($record) => $record
                        ? ($record->courseOffering->course->code ?? '').' — '
                            .trim(($record->student->first_name ?? '').' '.($record->student->last_name ?? ''))
                            .' ('.$record->student->student_id_number.') — '
                            .$record->student->email
                        : '—'),

                Textarea::make('narrative')
                    ->disabled()
                    ->rows(5)
                    ->columnSpanFull(),

                Textarea::make('other_notes')
                    ->label('Other notes')
                    ->disabled()
                    ->rows(3)
                    ->columnSpanFull(),

                Placeholder::make('dean_confirmed_display')
                    ->label('Assistant Dean confirmed?')
                    ->content(fn ($record) => $record && $record->dean_confirmed ? 'Yes' : 'No'),

                Placeholder::make('evidence_link')
                    ->label('Evidence')
                    ->content(fn ($record) => $record?->evidence_path
                        ? 'Attached (see detail view)'
                        : 'None uploaded'),

                Select::make('status')
                    ->options([
                        MissedAssessmentAppeal::STATUS_PENDING => 'Pending',
                        MissedAssessmentAppeal::STATUS_UNDER_REVIEW => 'Under review',
                        MissedAssessmentAppeal::STATUS_APPROVED => 'Approved',
                        MissedAssessmentAppeal::STATUS_REJECTED => 'Rejected',
                        MissedAssessmentAppeal::STATUS_PARTIALLY_APPROVED => 'Partially approved',
                    ])
                    ->required(),
            ]);
    }
}
