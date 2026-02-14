<?php

namespace Database\Factories;

use App\Models\GradeQuery;
use App\Models\GradeQueryMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GradeQueryMessage>
 */
class GradeQueryMessageFactory extends Factory
{
    protected $model = GradeQueryMessage::class;

    public function definition(): array
    {
        return [
            'grade_query_id' => GradeQuery::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
            'is_internal_note' => false,
        ];
    }
}
