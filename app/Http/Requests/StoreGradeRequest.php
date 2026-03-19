<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'exists:students,id'],
            'assessment_id' => ['required', 'exists:assessments,id'],
            'grade' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_id.required' => 'A student is required.',
            'student_id.exists' => 'The selected student does not exist.',
            'assessment_id.required' => 'An assessment is required.',
            'assessment_id.exists' => 'The selected assessment does not exist.',
            'grade.required' => 'A grade is required.',
            'grade.numeric' => 'The grade must be a number.',
            'grade.min' => 'The grade must be at least 0.',
            'grade.max' => 'The grade must not exceed 100.',
        ];
    }
}
