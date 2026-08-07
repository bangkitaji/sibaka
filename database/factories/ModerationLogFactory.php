<?php

namespace Database\Factories;

use App\Enums\ModerationAction;
use App\Models\ModerationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ModerationLog>
 */
class ModerationLogFactory extends Factory
{
    protected $model = ModerationLog::class;

    public function definition(): array
    {
        $action = fake()->randomElement(ModerationAction::cases());

        return [
            'moderator_id' => User::factory(),
            'target_user_id' => fake()->optional(0.7)->uuid(),
            'target_content_id' => fake()->optional(0.5)->uuid(),
            'action' => $action,
            'reason' => fake()->sentence(fake()->numberBetween(5, 20)),
            'created_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }

    public function removeContent(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => ModerationAction::RemoveContent,
        ]);
    }

    public function suspendUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => ModerationAction::SuspendUser,
        ]);
    }

    public function issueWarning(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => ModerationAction::IssueWarning,
        ]);
    }

    public function dismiss(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => ModerationAction::Dismiss,
        ]);
    }
}
