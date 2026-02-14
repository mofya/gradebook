<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GradeQueryResource\Pages;
use App\Filament\Resources\GradeQueryResource\RelationManagers;
use App\Models\GradeQuery;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class GradeQueryResource extends Resource
{
    protected static ?string $model = GradeQuery::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'Grade Queries';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Query Details')
                    ->schema([
                        Forms\Components\Select::make('student_id')
                            ->relationship('student', 'email')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled(fn (string $operation): bool => $operation === 'edit'),
                        Forms\Components\Select::make('enrollment_id')
                            ->relationship('enrollment')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->courseOffering->course->code)
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('assessment_id')
                            ->relationship('assessment', 'name')
                            ->nullable()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('subject')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Messages')
                    ->schema([
                        Forms\Components\Textarea::make('student_message')
                            ->required(),
                        Forms\Components\Textarea::make('lecturer_response')
                            ->nullable(),
                    ])
                    ->columns(1),

                Section::make('Status & Assignment')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'open' => 'Open',
                                'under_review' => 'Under Review',
                                'resolved' => 'Resolved',
                                'rejected' => 'Rejected',
                            ])
                            ->required(),
                        Forms\Components\Select::make('priority')
                            ->options([
                                'low' => 'Low',
                                'normal' => 'Normal',
                                'high' => 'High',
                                'urgent' => 'Urgent',
                            ])
                            ->required()
                            ->default('normal'),
                        Forms\Components\Select::make('assigned_to')
                            ->relationship('assignedTo', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Unassigned'),
                    ])
                    ->columns(3),

                Section::make('Resolution')
                    ->schema([
                        Forms\Components\Select::make('resolved_by')
                            ->relationship('resolvedBy', 'name')
                            ->nullable()
                            ->searchable()
                            ->preload(),
                        Forms\Components\DateTimePicker::make('resolved_at')
                            ->nullable(),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student.email')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('enrollment.courseOffering.course.code')
                    ->label('Course'),
                Tables\Columns\TextColumn::make('assessment.name')
                    ->label('Assessment'),
                Tables\Columns\TextColumn::make('subject')
                    ->sortable()
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'info',
                        'under_review' => 'warning',
                        'resolved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'low' => 'gray',
                        'normal' => 'info',
                        'high' => 'warning',
                        'urgent' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('assignedTo.name')
                    ->label('Assigned To')
                    ->placeholder('Unassigned'),
                Tables\Columns\TextColumn::make('created_at')
                    ->since()
                    ->label('Submitted'),
                Tables\Columns\TextColumn::make('resolved_at')
                    ->since()
                    ->label('Resolved'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MessagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGradeQueries::route('/'),
            'create' => Pages\CreateGradeQuery::route('/create'),
            'edit' => Pages\EditGradeQuery::route('/{record}/edit'),
        ];
    }
}
