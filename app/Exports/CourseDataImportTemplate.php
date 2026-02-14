<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class CourseDataImportTemplate implements FromArray, ShouldAutoSize
{
    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return [
            // Header row — the importer auto-detects column roles from these
            ['S/N', 'Student ID', 'Name', 'Gender', 'Programme', 'Quiz 1 (10)', 'Quiz 2 (10)', 'Assignment 1 (20)', 'Test 1 (30)', 'Test 2 (30)', 'Exam (100)'],
            // Sample data rows
            [1, 'SN202100123', 'Mwansa Chanda', 'F', 'Computer Science', 8, 7, 16, 24, 22, 68],
            [2, 'SN202100456', 'Bwalya Mulenga', 'M', 'Computer Science', 6, 9, 18, 27, 25, 72],
            [3, 'SN202200789', 'Thandiwe Banda', 'F', 'Information Technology', 9, 8, 14, 20, 18, 55],
            [4, 'SN202200321', 'Chilufya Tembo', 'M', 'Information Technology', 5, 6, 12, 15, 19, 48],
            [5, 'SN202300654', 'Mutinta Phiri', 'F', 'Computer Science', 10, 10, 19, 28, 26, 82],
        ];
    }
}
