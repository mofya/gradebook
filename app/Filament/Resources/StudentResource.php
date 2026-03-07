<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentResource\Pages;
use App\Filament\Resources\StudentResource\RelationManagers;
use App\Models\Student;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Students';

    protected static string|\UnitEnum|null $navigationGroup = 'Students & Grading';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Personal Information')
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('last_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->required()
                            ->email()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('student_id_number')
                            ->unique(ignoreRecord: true)
                            ->nullable(),
                        Forms\Components\Select::make('gender')
                            ->options(['Male' => 'Male', 'Female' => 'Female'])
                            ->nullable(),
                    ])
                    ->columns(2),

                Section::make('Academic Details')
                    ->schema([
                        Forms\Components\TextInput::make('program')
                            ->nullable(),
                        Forms\Components\TextInput::make('year_of_study')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(7)
                            ->nullable(),
                        Forms\Components\Select::make('study_mode')
                            ->options([
                                'Full-time' => 'Full-time',
                                'Part-time' => 'Part-time',
                                'Distance' => 'Distance',
                            ])
                            ->nullable(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Personal Information')
                    ->icon(Heroicon::OutlinedUser)
                    ->schema([
                        TextEntry::make('first_name'),
                        TextEntry::make('last_name'),
                        TextEntry::make('email')
                            ->icon(Heroicon::OutlinedEnvelope),
                        TextEntry::make('student_id_number')
                            ->label('Student ID')
                            ->placeholder('Not provided'),
                        TextEntry::make('gender')
                            ->placeholder('Not specified'),
                    ])
                    ->columns(2),

                Section::make('Academic Details')
                    ->icon(Heroicon::OutlinedAcademicCap)
                    ->schema([
                        TextEntry::make('program')
                            ->placeholder('Not specified'),
                        TextEntry::make('year_of_study')
                            ->placeholder('Not specified'),
                        TextEntry::make('study_mode')
                            ->badge()
                            ->placeholder('Not specified'),
                        TextEntry::make('created_at')
                            ->label('Registered')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('first_name')
                    ->label('First Name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->label('Last Name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('student_id_number')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('program')
                    ->sortable(),
                Tables\Columns\TextColumn::make('year_of_study')
                    ->sortable(),
                Tables\Columns\TextColumn::make('study_mode')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered At')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('last_name', 'asc')
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->filters([
                SelectFilter::make('gender')
                    ->options([
                        'Male' => 'Male',
                        'Female' => 'Female',
                    ]),
                SelectFilter::make('year_of_study')
                    ->options([
                        '1' => 'Year 1',
                        '2' => 'Year 2',
                        '3' => 'Year 3',
                        '4' => 'Year 4',
                        '5' => 'Year 5',
                        '6' => 'Year 6',
                        '7' => 'Year 7',
                    ]),
                SelectFilter::make('study_mode')
                    ->options([
                        'Full-time' => 'Full-time',
                        'Part-time' => 'Part-time',
                        'Distance' => 'Distance',
                    ]),
                SelectFilter::make('program')
                    ->searchable()
                    ->preload()
                    ->options(fn (): array => Student::query()
                        ->whereNotNull('program')
                        ->distinct()
                        ->orderBy('program')
                        ->pluck('program', 'program')
                        ->all()),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->modalHeading('Delete Student')
                    ->modalDescription('Are you sure? This will remove the student and all their enrollment records.'),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make()
                    ->modalHeading('Delete Selected Students')
                    ->modalDescription('Are you sure? This will remove the selected students and all their enrollment records.'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CoursesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudents::route('/'),
            'create' => Pages\CreateStudent::route('/create'),
            'view' => Pages\ViewStudent::route('/{record}'),
            'edit' => Pages\EditStudent::route('/{record}/edit'),
        ];
    }
}
