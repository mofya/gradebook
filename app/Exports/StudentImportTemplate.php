<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StudentImportTemplate implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['student_id', 'first_name', 'last_name', 'email', 'gender', 'program', 'year_of_study'];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return [
            ['SN202100123', 'Mwansa', 'Chanda', 'mwansa.chanda@student.unza.zm', 'Female', 'Computer Science', '3'],
            ['SN202100456', 'Bwalya', 'Mulenga', 'bwalya.mulenga@student.unza.zm', 'Male', 'Computer Science', '3'],
            ['SN202200789', 'Thandiwe', 'Banda', 'thandiwe.banda@student.unza.zm', 'Female', 'Information Technology', '2'],
            ['SN202200321', 'Chilufya', 'Tembo', 'chilufya.tembo@student.unza.zm', 'Male', 'Information Technology', '2'],
            ['SN202300654', 'Mutinta', 'Phiri', 'mutinta.phiri@student.unza.zm', 'Female', 'Computer Science', '1'],
        ];
    }
}
