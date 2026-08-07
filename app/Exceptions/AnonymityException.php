<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class AnonymityException extends RuntimeException
{
    public static function rateLimitReached(): self
    {
        return new self('Anonymous posting limit reached (5 per 24 hours)', 429);
    }

    public static function cannotRevealIdentity(): self
    {
        return new self('Anonymous content cannot be converted to non-anonymous', 403);
    }

    public static function unauthorizedModeration(): self
    {
        return new self('Only moderators and admins can access anonymous author identity', 403);
    }

    public static function metadataNotFound(): self
    {
        return new self('Anonymous metadata not found for this content', 404);
    }
}
