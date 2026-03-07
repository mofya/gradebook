<?php

namespace App\Filament\Resources\GradeQueryResource\Pages;

use App\Filament\Resources\GradeQueryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGradeQuery extends EditRecord
{
    protected static string $resource = GradeQueryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->modalHeading('Delete Grade Query')
                ->modalDescription('Are you sure? This will permanently remove this query and its messages.'),
        ];
    }
}
