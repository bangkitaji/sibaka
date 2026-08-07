<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Profile>
 */
class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    private const JOB_TITLES = [
        'Software Engineer',
        'Senior Backend Developer',
        'DevOps Engineer',
        'Data Engineer',
        'Frontend Developer',
        'Full Stack Developer',
        'Site Reliability Engineer',
        'Cloud Architect',
        'Technical Lead',
        'Engineering Manager',
        'Product Manager',
        'Security Engineer',
        'Mobile Developer',
        'QA Engineer',
        'System Administrator',
    ];

    private const COMPANIES = [
        'Tokopedia',
        'Gojek',
        'Shopee',
        'Bukalapak',
        'Traveloka',
        'OVO',
        'Dana',
        'Tiket.com',
        'Blibli',
        'Ruangguru',
        'Xendit',
        'Mekari',
        'Kitabisa',
        'Amartha',
        'eFishery',
    ];

    private const TECH_STACKS = [
        'Laravel, PHP, PostgreSQL, Redis',
        'React, TypeScript, Node.js, MongoDB',
        'Python, Django, Celery, PostgreSQL',
        'Go, gRPC, Kubernetes, AWS',
        'Java, Spring Boot, Kafka, MySQL',
        'Vue.js, Nuxt, Firebase, GCP',
        'Rust, WebAssembly, Docker, Linux',
        'Flutter, Dart, Firebase, GCP',
        'Kotlin, Android, Jetpack Compose',
        'Swift, iOS, CoreData, CloudKit',
    ];

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'job_title' => fake()->randomElement(self::JOB_TITLES),
            'company' => fake()->randomElement(self::COMPANIES),
            'years_of_experience' => fake()->numberBetween(0, 30),
            'primary_tech_stack' => fake()->randomElement(self::TECH_STACKS),
            'secondary_tech_stack' => fake()->optional(0.6)->randomElement(self::TECH_STACKS),
            'mentorship_status' => fake()->optional(0.5)->randomElement(['willing', 'not_willing']),
            'hiring_status' => fake()->optional(0.5)->randomElement(['open_to_hiring', 'seeking_job', 'internship', 'none']),
            'availability' => fake()->optional(0.4)->randomElement(['immediate', '1_month', '2_months', '3_months_plus']),
            'linkedin_url' => fake()->optional(0.7)->url(),
            'github_url' => fake()->optional(0.6)->url(),
            'completion_percentage' => fake()->numberBetween(20, 100),
        ];
    }

    public function complete(): static
    {
        return $this->state(fn (array $attributes) => [
            'completion_percentage' => 100,
            'mentorship_status' => 'willing',
            'hiring_status' => 'open_to_hiring',
            'availability' => 'immediate',
            'linkedin_url' => fake()->url(),
            'github_url' => fake()->url(),
        ]);
    }

    public function incomplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'job_title' => null,
            'company' => null,
            'years_of_experience' => null,
            'primary_tech_stack' => null,
            'completion_percentage' => 0,
        ]);
    }
}
