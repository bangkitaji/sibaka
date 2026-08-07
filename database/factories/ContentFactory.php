<?php

namespace Database\Factories;

use App\Enums\ContentCategory;
use App\Enums\ContentStatus;
use App\Models\Content;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Content>
 */
class ContentFactory extends Factory
{
    protected $model = Content::class;

    public function definition(): array
    {
        $status = fake()->randomElement(ContentStatus::cases());
        $publishedAt = $status === ContentStatus::Published ? fake()->dateTimeBetween('-1 year', 'now') : null;

        return [
            'author_id' => User::factory(),
            'title' => fake()->sentence(fake()->numberBetween(4, 12)),
            'body' => fake()->paragraphs(fake()->numberBetween(2, 8), true),
            'body_html' => '<p>' . fake()->paragraphs(fake()->numberBetween(2, 8), true) . '</p>',
            'category' => fake()->randomElement(ContentCategory::cases()),
            'is_anonymous' => fake()->boolean(20),
            'is_qna' => fake()->boolean(30),
            'accepted_solution_id' => null,
            'status' => $status,
            'is_locked' => fake()->boolean(10),
            'locked_at' => null,
            'published_at' => $publishedAt,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContentStatus::Published,
            'published_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContentStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_anonymous' => true,
        ]);
    }

    public function qna(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_qna' => true,
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_locked' => true,
            'locked_at' => now(),
        ]);
    }

    public function postMortem(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => ContentCategory::PostMortem,
        ]);
    }

    public function techStack(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => ContentCategory::TechStack,
        ]);
    }

    public function careerInterview(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => ContentCategory::CareerInterview,
        ]);
    }

    public function showcase(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => ContentCategory::Showcase,
        ]);
    }
}
