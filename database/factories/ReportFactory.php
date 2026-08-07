<?php

namespace Database\Factories;

use App\Enums\ReportReason;
use App\Models\Content;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        return [
            'content_id' => Content::factory(),
            'reporter_id' => User::factory(),
            'reason' => fake()->randomElement(ReportReason::cases()),
            'description' => fake()->optional(0.6)->sentence(),
            'status' => fake()->randomElement(['pending', 'reviewed', 'dismissed']),
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);
    }

    public function reviewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'reviewed',
            'reviewed_by' => User::factory(),
            'reviewed_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    public function dismissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'dismissed',
            'reviewed_by' => User::factory(),
            'reviewed_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ]);
    }
}
