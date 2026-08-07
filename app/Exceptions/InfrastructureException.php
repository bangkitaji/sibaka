<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Exception for infrastructure-level failures (disk, network, external services).
 */
class InfrastructureException extends RuntimeException
{
    public static function diskFull(): self
    {
        return new self(
            'Storage capacity is temporarily full. File uploads are currently disabled. Please try again later.',
            507
        );
    }

    public static function emailServiceUnavailable(): self
    {
        return new self(
            'Email delivery is temporarily unavailable. Your notification has been queued and will be delivered when the service recovers.',
            503
        );
    }

    public static function redisUnavailable(): self
    {
        return new self(
            'Cache service is temporarily unavailable. The application is running in degraded mode.',
            503
        );
    }
}
