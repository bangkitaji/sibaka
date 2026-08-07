<?php

namespace Database\Factories;

use App\Models\Content;
use App\Models\Draft;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Draft>
 */
class DraftFactory extends Factory
{
    protected $model = Draft::class;

    public function definition(): array
    {
        return [
            'content_id' => Content::factory(),
            'author_id' => User::factory(),
            'body' => fake()->paragraphs(fake()->numberBetween(1, 5), true),
            'saved_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ];
    }
}
