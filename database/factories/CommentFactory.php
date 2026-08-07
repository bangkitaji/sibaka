<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Content;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'content_id' => Content::factory(),
            'author_id' => User::factory(),
            'parent_id' => null,
            'body' => fake()->paragraph(fake()->numberBetween(1, 4)),
            'is_anonymous' => fake()->boolean(15),
            'is_edited' => false,
            'depth' => 0,
            'edited_at' => null,
        ];
    }

    public function withDepth(int $depth): static
    {
        return $this->state(fn (array $attributes) => [
            'depth' => min($depth, 5),
        ]);
    }

    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_anonymous' => true,
        ]);
    }

    public function edited(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_edited' => true,
            'edited_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ]);
    }

    public function reply(Comment $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'content_id' => $parent->content_id,
            'parent_id' => $parent->id,
            'depth' => min($parent->depth + 1, 5),
        ]);
    }
}
