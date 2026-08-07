<?php

namespace Database\Factories;

use App\Models\InviteCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InviteCode>
 */
class InviteCodeFactory extends Factory
{
    protected $model = InviteCode::class;

    public function definition(): array
    {
        return [
            'generated_by' => User::factory(),
            'code' => Str::upper(Str::random(8)),
            'is_used' => false,
            'used_by' => null,
            'expires_at' => fake()->dateTimeBetween('+1 day', '+30 days'),
        ];
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_used' => true,
            'used_by' => User::factory(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => fake()->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }

    public function valid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_used' => false,
            'used_by' => null,
            'expires_at' => fake()->dateTimeBetween('+1 day', '+30 days'),
        ]);
    }
}
