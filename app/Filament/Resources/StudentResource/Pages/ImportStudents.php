<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
use Filament\Resources\Pages\Page;

class ImportStudents extends Page
{
    protected static string $resource = StudentResource::class;

    protected string $view = 'filament.resources.student-resource.pages.import-students';
}
