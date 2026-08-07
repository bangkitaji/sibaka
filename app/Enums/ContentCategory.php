<?php

declare(strict_types=1);

namespace App\Enums;

enum ContentCategory: string
{
    case PostMortem = 'post_mortem';
    case TechStack = 'tech_stack';
    case CareerInterview = 'career_interview';
    case Showcase = 'showcase';

    public function label(): string
    {
        return match ($this) {
            self::PostMortem => 'Post-Mortem/Incident Case',
            self::TechStack => 'Tech Stack & Architecture',
            self::CareerInterview => 'Career & Interview',
            self::Showcase => 'Showcase/Side Project',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PostMortem => 'Documenting system failures, security incidents, and technical problem-solving experiences',
            self::TechStack => 'Sharing technology decisions, system designs, and architectural patterns',
            self::CareerInterview => 'Sharing job search experiences, interview questions, and career development advice',
            self::Showcase => 'Displaying personal projects, open source contributions, and portfolio work',
        };
    }
}
