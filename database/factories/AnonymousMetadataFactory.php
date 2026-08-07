<?php

namespace Database\Factories;

use App\Models\AnonymousMetadata;
use App\Models\Content;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AnonymousMetadata>
 */
class AnonymousMetadataFactory extends Factory
{
    protected $model = AnonymousMetadata::class;

    public function definition(): array
    {
        return [
            'content_id' => Content::factory(),
            'author_id' => User::factory(),
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'user_agent' => fake()->userAgent(),
            'browser_fingerprint' => hash('sha256', fake()->uuid()),
            'expires_at' => now()->addDays(90),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => fake()->dateTimeBetween('-60 days', '-1 day'),
        ]);
    }
}
