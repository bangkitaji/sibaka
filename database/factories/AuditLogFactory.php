<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    private const ACTION_TYPES = [
        'login',
        'logout',
        'register',
        'password_reset',
        'profile_update',
        'content_create',
        'content_update',
        'content_delete',
        'comment_create',
        'comment_delete',
        'report_submit',
        'moderation_action',
        'verification_approve',
        'verification_reject',
        'account_suspend',
        'account_lock',
    ];

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action_type' => fake()->randomElement(self::ACTION_TYPES),
            'ip_address' => fake()->ipv4(),
            'affected_resource' => fake()->randomElement(['user', 'content', 'comment', 'report']) . ':' . fake()->uuid(),
            'metadata' => fake()->optional(0.5)->randomElement([
                ['browser' => 'Chrome', 'os' => 'Windows'],
                ['method' => 'POST', 'path' => '/api/v1/content'],
                ['previous_role' => 'pending', 'new_role' => 'member'],
            ]),
            'created_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
