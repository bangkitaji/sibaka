<?php

declare(strict_types=1);

namespace App\Enums;

enum ReactionType: string
{
    case Insightful = 'insightful';
    case Relatable = 'relatable';
    case Helpful = 'helpful';
    case Solutif = 'solutif';
}
