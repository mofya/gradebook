<?php

namespace App\Filament\Resources\Departments\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DepartmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Department Details')
                    ->description('Define the department name and its unique code.')
                    ->icon('heroicon-o-building-library')
                    ->schema([
                        TextInput::make('dept_name')
                            ->label('Department Name')
                            ->placeholder('e.g. Computing and Informatics')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('dept_code')
                            ->label('Department Code')
                            ->placeholder('e.g. DCI')
                            ->required()
                            ->maxLength(10),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
