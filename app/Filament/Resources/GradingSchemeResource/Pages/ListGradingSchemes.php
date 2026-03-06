<?php

namespace App\Filament\Resources\GradingSchemeResource\Pages;

use App\Filament\Resources\GradingSchemeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGradingSchemes extends ListRecords
{
    protected static string $resource = GradingSchemeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
