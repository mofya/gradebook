<?php

namespace App\Filament\Resources\GradeQueryResource\Pages;

use App\Filament\Resources\GradeQueryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGradeQueries extends ListRecords
{
    protected static string $resource = GradeQueryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
