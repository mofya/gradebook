<?php

namespace App\Filament\Resources\GradingSchemeResource\Pages;

use App\Filament\Resources\GradingSchemeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGradingScheme extends EditRecord
{
    protected static string $resource = GradingSchemeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->modalHeading('Delete Grading Scheme')
                ->modalDescription('Are you sure? This will remove the scheme and its grade levels.'),
        ];
    }
}
