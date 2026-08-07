<?php

namespace Database\Seeders;

use App\Enums\TagCategory;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            TagCategory::TechStack->value => [
                'kubernetes',
                'docker',
                'python',
                'react',
                'vue',
                'angular',
                'nodejs',
                'golang',
                'java',
                'php',
                'typescript',
                'rust',
                'aws',
                'gcp',
                'azure',
                'terraform',
                'postgresql',
                'mongodb',
                'redis',
                'elasticsearch',
                'kafka',
                'rabbitmq',
                'nginx',
                'linux',
                'git',
            ],
            TagCategory::ExperienceLevel->value => [
                'beginner',
                'intermediate',
                'advanced',
                'architecture',
            ],
            TagCategory::Category->value => [
                'incident',
                'architecture',
                'career',
                'project',
            ],
        ];

        foreach ($tags as $category => $names) {
            foreach ($names as $name) {
                Tag::firstOrCreate(
                    ['name' => $name],
                    ['tag_category' => $category]
                );
            }
        }
    }
}
