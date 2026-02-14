<?php

namespace App\Filament\Pages;

use App\Models\GradeAuditLog;
use BackedEnum;
use Filament\Pages\Page;

class AuditLog extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Audit Log';

    protected string $view = 'filament.pages.audit-log';

    public int $perPage = 25;

    public function getLogsProperty(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return GradeAuditLog::query()
            ->with(['user', 'auditable'])
            ->latest()
            ->paginate($this->perPage);
    }
}
