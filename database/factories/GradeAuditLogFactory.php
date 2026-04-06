<?php

namespace Database\Factories;

use App\Models\GradeAuditLog;
use App\Models\GradeResult;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeAuditLog>
 */
class GradeAuditLogFactory extends Factory
{
    protected $model = GradeAuditLog::class;

    public function definition(): array
    {
        return [
            'auditable_type' => GradeResult::class,
            'auditable_id' => GradeResult::factory(),
            'user_id' => User::factory(),
            'action' => fake()->randomElement(['created', 'updated', 'deleted']),
            'old_values' => null,
            'new_values' => ['raw_score' => fake()->randomFloat(2, 0, 100)],
            'reason' => fake()->optional()->sentence(),
            'ip_address' => fake()->ipv4(),
        ];
    }
}
