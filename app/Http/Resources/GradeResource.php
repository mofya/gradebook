<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GradeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mark' => $this->grade,
            'grade_letter' => $this->grade_letter,
            'is_published' => $this->is_published,
            'course' => $this->whenLoaded('course', fn () => [
                'id' => $this->course->id,
                'code' => $this->course->code,
                'name' => $this->course->name,
            ]),
            'assessment' => $this->whenLoaded('assessment', fn () => [
                'id' => $this->assessment->id,
                'name' => $this->assessment->name,
                'weight' => $this->assessment->weight,
            ]),
            'lecturer' => $this->whenLoaded('lecturer', fn () => [
                'id' => $this->lecturer->id,
                'name' => $this->lecturer->name,
            ]),
        ];
    }
}
