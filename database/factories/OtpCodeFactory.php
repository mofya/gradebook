<?php

namespace Database\Factories;

use App\Models\OtpCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OtpCode>
 */
class OtpCodeFactory extends Factory
{
    protected $model = OtpCode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = str_pad((string) fake()->numberBetween(0, 999999), 6, '0', STR_PAD_LEFT);

        return [
            'email' => fake()->unique()->safeEmail(),
            'code' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'verified_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinutes(1),
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_at' => now(),
        ]);
    }

    public function maxAttempts(): static
    {
        return $this->state(fn (array $attributes) => [
            'attempts' => 5,
        ]);
    }
}
