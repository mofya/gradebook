<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GradeResource\Pages;
use App\Models\Assessment;
use App\Models\Grade;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class GradeResource extends Resource
{
    protected static ?string $model = Grade::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Grades';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('student_id')
                    ->label('Student')
                    ->relationship('student', 'email')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('course_id')
                    ->label('Course')
                    ->relationship('course', 'name')
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($state, callable $set) => $set('assessment_id', null)),
                Forms\Components\Select::make('assessment_id')
                    ->label('Assessment')
                    ->options(function ($get) {
                        $courseId = $get('course_id');
                        if ($courseId) {
                            return Assessment::where('course_id', $courseId)->pluck('name', 'id');
                        }

                        return [];
                    })
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('grade')
                    ->label('Grade')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student.email')
                    ->label('Student Email')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('course.name')
                    ->label('Course')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('assessment.name')
                    ->label('Assessment')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('grade')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Recorded At')
                    ->dateTime(),
            ])
            ->filters([
                // Add any necessary filters
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(), // Optionally allow deletion
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Define any relation managers if needed
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGrades::route('/'),
            'create' => Pages\CreateGrade::route('/create'),
            'edit' => Pages\EditGrade::route('/{record}/edit'),
        ];
    }
}
