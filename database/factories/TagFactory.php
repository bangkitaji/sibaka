<?php

namespace Database\Factories;

use App\Enums\TagCategory;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(2),
            'tag_category' => fake()->randomElement(TagCategory::cases()),
        ];
    }

    public function techStack(): static
    {
        return $this->state(fn (array $attributes) => [
            'tag_category' => TagCategory::TechStack,
        ]);
    }

    public function experienceLevel(): static
    {
        return $this->state(fn (array $attributes) => [
            'tag_category' => TagCategory::ExperienceLevel,
        ]);
    }

    public function category(): static
    {
        return $this->state(fn (array $attributes) => [
            'tag_category' => TagCategory::Category,
        ]);
    }
}
