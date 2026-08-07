<?php

namespace Database\Factories;

use App\Models\PortalMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PortalMessage>
 */
class PortalMessageFactory extends Factory
{
    protected $model = PortalMessage::class;

    public function definition(): array
    {
        return [
            'sender_id' => User::factory(),
            'recipient_id' => User::factory(),
            'body' => fake()->paragraph(fake()->numberBetween(1, 3)),
            'is_read' => fake()->boolean(40),
        ];
    }

    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => false,
        ]);
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
        ]);
    }
}
