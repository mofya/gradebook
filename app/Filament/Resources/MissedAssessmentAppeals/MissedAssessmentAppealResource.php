<?php

namespace App\Filament\Resources\MissedAssessmentAppeals;

use App\Filament\Resources\MissedAssessmentAppeals\Pages\EditMissedAssessmentAppeal;
use App\Filament\Resources\MissedAssessmentAppeals\Pages\ListMissedAssessmentAppeals;
use App\Filament\Resources\MissedAssessmentAppeals\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\MissedAssessmentAppeals\Schemas\MissedAssessmentAppealForm;
use App\Filament\Resources\MissedAssessmentAppeals\Tables\MissedAssessmentAppealsTable;
use App\Models\MissedAssessmentAppeal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MissedAssessmentAppealResource extends Resource
{
    protected static ?string $model = MissedAssessmentAppeal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Students & Grading';

    protected static ?string $navigationLabel = 'Missed Assessment Appeals';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return MissedAssessmentAppealForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MissedAssessmentAppealsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        // Students submit via the public form; admins only review.
        return false;
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMissedAssessmentAppeals::route('/'),
            'edit' => EditMissedAssessmentAppeal::route('/{record}/edit'),
        ];
    }
}
