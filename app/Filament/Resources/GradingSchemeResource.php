<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GradingSchemeResource\Pages;
use App\Filament\Resources\GradingSchemeResource\RelationManagers;
use App\Models\GradingScheme;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class GradingSchemeResource extends Resource
{
    protected static ?string $model = GradingScheme::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-vertical';

    protected static string|\UnitEnum|null $navigationGroup = 'Academic Setup';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Scheme')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Toggle::make('is_default'),
                    ])
                    ->columns(2),

                Section::make('Rounding Rules')
                    ->schema([
                        Forms\Components\Select::make('rounding_rule')
                            ->options(['round' => 'Round', 'floor' => 'Floor', 'ceil' => 'Ceiling'])
                            ->required(),
                        Forms\Components\TextInput::make('decimal_places')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(4),
                        Forms\Components\TextInput::make('rounding_precision')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(4)
                            ->helperText('Number of decimal places for rounding'),
                        Forms\Components\Select::make('boundary_behavior')
                            ->options([
                                'inclusive_lower' => 'Inclusive Lower (mark >= min)',
                                'inclusive_upper' => 'Inclusive Upper (mark <= max)',
                            ])
                            ->required()
                            ->default('inclusive_lower'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->boolean(),
                Tables\Columns\TextColumn::make('rounding_rule'),
                Tables\Columns\TextColumn::make('decimal_places')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('rounding_precision')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('boundary_behavior')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'inclusive_lower' => 'info',
                        'inclusive_upper' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->filters([
                TernaryFilter::make('is_default'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->modalHeading('Delete Grading Scheme')
                    ->modalDescription('Are you sure? This will remove the scheme and its grade levels.'),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make()
                    ->modalHeading('Delete Selected Grading Schemes')
                    ->modalDescription('Are you sure? This will remove the selected schemes and their grade levels.'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LevelsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGradingSchemes::route('/'),
            'create' => Pages\CreateGradingScheme::route('/create'),
            'edit' => Pages\EditGradingScheme::route('/{record}/edit'),
        ];
    }
}
