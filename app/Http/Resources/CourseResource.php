<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'credits' => $this->credits,
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department->id,
                'name' => $this->department->dept_name,
                'code' => $this->department->dept_code,
            ]),
            'year' => $this->whenLoaded('year', fn () => $this->year->name),
            'assessments' => $this->whenLoaded('assessments', fn () => $this->assessments->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'weight' => $a->weight,
            ])),
        ];
    }
}
