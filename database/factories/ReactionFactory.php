<?php

namespace Database\Factories;

use App\Enums\ReactionType;
use App\Models\Content;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reaction>
 */
class ReactionFactory extends Factory
{
    protected $model = Reaction::class;

    public function definition(): array
    {
        return [
            'content_id' => Content::factory(),
            'user_id' => User::factory(),
            'type' => fake()->randomElement(ReactionType::cases()),
        ];
    }

    public function insightful(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ReactionType::Insightful,
        ]);
    }

    public function relatable(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ReactionType::Relatable,
        ]);
    }

    public function helpful(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ReactionType::Helpful,
        ]);
    }

    public function solutif(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ReactionType::Solutif,
        ]);
    }
}
