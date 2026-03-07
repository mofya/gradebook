<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;

enum OfferingStatus: string implements HasColor
{
    case Draft = 'draft';
    case Active = 'active';
    case Locked = 'locked';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Locked => 'Locked',
            self::Published => 'Published',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'info',
            self::Locked => 'warning',
            self::Published => 'success',
        };
    }
}
