<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\ServiceProvider;

/**
 * Handles graceful degradation when infrastructure services are unavailable.
 *
 * - Redis unavailable: falls back to database sessions and file cache
 * - Detects Redis availability at boot and reconfigures drivers accordingly
 */
class GracefulDegradationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * Checks Redis connectivity and reconfigures session/cache drivers
     * to database/file fallbacks if Redis is unreachable.
     */
    public function boot(): void
    {
        if ($this->isRunningInConsoleWithoutRedisNeeded()) {
            return;
        }

        if (! $this->isRedisAvailable()) {
            $this->fallbackFromRedis();
        }
    }

    /**
     * Determine if Redis is reachable.
     */
    protected function isRedisAvailable(): bool
    {
        try {
            Redis::connection()->ping();

            return true;
        } catch (\Throwable $e) {
            Log::warning('Redis is unavailable, activating graceful degradation', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Reconfigure session and cache drivers to non-Redis alternatives.
     */
    protected function fallbackFromRedis(): void
    {
        // Switch session driver to database
        config(['session.driver' => 'database']);

        // Switch cache store to file
        config(['cache.default' => 'file']);

        // Switch queue connection to database (so jobs still process)
        config(['queue.default' => 'database']);

        Log::info('Graceful degradation active: session=database, cache=file, queue=database');
    }

    /**
     * Skip Redis check for console commands that don't need it (e.g., migrations).
     */
    protected function isRunningInConsoleWithoutRedisNeeded(): bool
    {
        if (! $this->app->runningInConsole()) {
            return false;
        }

        // Allow Redis check for queue workers and scheduled tasks
        $argv = $_SERVER['argv'] ?? [];
        $command = $argv[1] ?? '';

        $redisNeededCommands = ['queue:work', 'queue:listen', 'schedule:run', 'schedule:work', 'horizon'];

        foreach ($redisNeededCommands as $needed) {
            if (str_contains($command, $needed)) {
                return false;
            }
        }

        return true;
    }
}
