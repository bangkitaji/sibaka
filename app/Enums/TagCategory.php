<?php

declare(strict_types=1);

namespace App\Enums;

enum TagCategory: string
{
    case TechStack = 'tech_stack';
    case ExperienceLevel = 'experience_level';
    case Category = 'category';
}
