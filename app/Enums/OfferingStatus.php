<?php

namespace App\Enums;

enum OfferingStatus: string
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
}
