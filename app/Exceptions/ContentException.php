<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class ContentException extends RuntimeException
{
    public static function locked(): self
    {
        return new self('Thread locked due to inactivity', 403);
    }

    public static function embedLimitReached(): self
    {
        return new self('Maximum embed limit of 10 reached', 422);
    }

    public static function characterLimitExceeded(): self
    {
        return new self('Content exceeds maximum 50,000 characters', 422);
    }

    public static function editWindowExpired(): self
    {
        return new self('Edit window has expired (15 minutes)', 403);
    }

    public static function notAuthor(): self
    {
        return new self('You are not the author of this comment', 403);
    }

    public static function notContentAuthor(): self
    {
        return new self('Only the content author can manage accepted solutions', 403);
    }
}
