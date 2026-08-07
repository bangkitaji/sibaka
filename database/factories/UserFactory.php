<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    private const DEPARTMENTS = [
        'Teknik Komputer',
        'Teknik Elektronika',
        'Teknik Listrik',
        'Teknik Mesin',
        'Teknik Bangunan',
    ];

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'entry_year' => fake()->optional()->numberBetween(1975, (int) date('Y')),
            'graduation_year' => fake()->numberBetween(1979, (int) date('Y')),
            'department' => fake()->randomElement(self::DEPARTMENTS),
            'role' => fake()->randomElement(UserRole::cases()),
            'verification_status' => fake()->randomElement(VerificationStatus::cases()),
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'is_suspended' => false,
            'suspended_until' => null,
            'last_login_at' => fake()->optional(0.7)->dateTimeBetween('-1 year', 'now'),
            'remember_token' => Str::random(10),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Pending,
            'verification_status' => VerificationStatus::Pending,
        ]);
    }

    public function member(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Member,
            'verification_status' => VerificationStatus::Approved,
        ]);
    }

    public function moderator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Moderator,
            'verification_status' => VerificationStatus::Approved,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
            'verification_status' => VerificationStatus::Approved,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_suspended' => true,
            'suspended_until' => fake()->dateTimeBetween('now', '+30 days'),
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(30),
        ]);
    }
}
