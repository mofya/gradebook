<?php

namespace App\Enums;

enum ExamStatus: string
{
    case NotEntered = 'NE';
    case Supplementary = 'SP';
    case Deferred = 'DV';
    case Exempt = 'EX';
    case Absent = 'ABS';
    case Withheld = 'WH';

    public function label(): string
    {
        return match ($this) {
            self::NotEntered => 'Not Entered',
            self::Supplementary => 'Supplementary',
            self::Deferred => 'Deferred',
            self::Exempt => 'Exempt',
            self::Absent => 'Absent',
            self::Withheld => 'Withheld',
        };
    }
}
