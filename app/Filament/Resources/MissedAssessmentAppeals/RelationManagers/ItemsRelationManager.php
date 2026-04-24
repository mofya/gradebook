<?php

namespace App\Filament\Resources\MissedAssessmentAppeals\RelationManagers;

use App\Models\MissedAssessmentAppealItem;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Appealed assessments';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('status')
                    ->options([
                        MissedAssessmentAppealItem::STATUS_PENDING => 'Pending',
                        MissedAssessmentAppealItem::STATUS_APPROVED => 'Approved',
                        MissedAssessmentAppealItem::STATUS_REJECTED => 'Rejected',
                    ])
                    ->required(),
                Textarea::make('reviewer_notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('assessment.name')
            ->columns([
                TextColumn::make('assessment.name')
                    ->label('Assessment')
                    ->searchable(),
                TextColumn::make('assessment.assessmentGroup.name')
                    ->label('Group')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => MissedAssessmentAppealItem::STATUS_PENDING,
                        'success' => MissedAssessmentAppealItem::STATUS_APPROVED,
                        'danger' => MissedAssessmentAppealItem::STATUS_REJECTED,
                    ]),
                TextColumn::make('reviewer_notes')
                    ->limit(60)
                    ->toggleable(),
            ])
            ->headerActions([])
            ->recordActions([
                EditAction::make()->label('Review'),
            ])
            ->toolbarActions([]);
    }
}
