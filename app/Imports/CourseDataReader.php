<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

class CourseDataReader implements ToArray
{
    /** @var array<int, array<int, mixed>> */
    protected array $data = [];

    public function array(array $array): void
    {
        $this->data = $array;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
