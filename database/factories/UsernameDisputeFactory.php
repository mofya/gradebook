<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\UsernameDispute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsernameDispute>
 */
class UsernameDisputeFactory extends Factory
{
    protected $model = UsernameDispute::class;

    public function definition(): array
    {
        return [
            'claimant_student_id' => Student::factory(),
            'current_holder_student_id' => Student::factory(),
            'github_username' => fake()->userName(),
            'status' => 'pending',
            'ip_address' => fake()->ipv4(),
        ];
    }
}
