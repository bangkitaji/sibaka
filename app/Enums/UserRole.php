<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Pending = 'pending';
    case Member = 'member';
    case Moderator = 'moderator';
    case Admin = 'admin';
}
