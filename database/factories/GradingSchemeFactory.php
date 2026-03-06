<?php

namespace Database\Factories;

use App\Models\GradingScheme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GradingScheme>
 */
class GradingSchemeFactory extends Factory
{
    protected $model = GradingScheme::class;

    public function definition(): array
    {
        return [
            'name' => 'UNZA Default Scale',
            'is_default' => true,
            'rounding_rule' => 'round',
            'decimal_places' => 0,
            'rounding_precision' => 0,
            'boundary_behavior' => 'inclusive_lower',
        ];
    }
}
