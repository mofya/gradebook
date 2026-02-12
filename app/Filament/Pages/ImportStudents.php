<?php

namespace App\Filament\Pages;

use App\Imports\StudentsImport;
use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

class ImportStudents extends Page
{
    use WithFileUploads;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-up-on-square';

    protected string $view = 'filament.pages.import-students';

    public $file;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\FileUpload::make('file')
                    ->label('Student Excel File')
                    ->required()
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel']),
            ]);
    }

    public function submit(): void
    {
        $this->validate();

        Excel::import(new StudentsImport, $this->file);

        Notification::make()
            ->title('Students imported successfully!')
            ->success()
            ->send();
    }
}
