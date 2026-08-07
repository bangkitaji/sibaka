<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Warning;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Warning>
 */
class WarningFactory extends Factory
{
    protected $model = Warning::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'issued_by' => User::factory(),
            'message' => fake()->sentence(fake()->numberBetween(5, 15)),
        ];
    }
}
