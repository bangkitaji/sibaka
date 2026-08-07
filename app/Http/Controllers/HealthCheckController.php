<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

/**
 * Health check endpoint that reports system infrastructure status.
 *
 * Returns degradation status for each component:
 * - Redis: checks connectivity, reports if fallback is active
 * - Database: checks query execution
 * - Disk: checks available storage space
 * - Email: reports queue health (failed jobs count)
 */
class HealthCheckController extends Controller
{
    /**
     * Minimum free disk space in bytes (50 MB).
     */
    private const MIN_FREE_BYTES = 52_428_800;

    /**
     * Return system health status.
     */
    public function __invoke(): JsonResponse
    {
        $status = [
            'status' => 'healthy',
            'services' => [
                'redis' => $this->checkRedis(),
                'database' => $this->checkDatabase(),
                'disk' => $this->checkDisk(),
                'email' => $this->checkEmail(),
            ],
            'degraded' => false,
        ];

        // Determine overall status
        $degradedServices = array_filter($status['services'], fn ($s) => $s['status'] !== 'healthy');

        if (! empty($degradedServices)) {
            $status['status'] = 'degraded';
            $status['degraded'] = true;
        }

        $criticalDown = array_filter($status['services'], fn ($s) => $s['status'] === 'critical');

        if (! empty($criticalDown)) {
            $status['status'] = 'critical';
        }

        $httpStatus = $status['status'] === 'healthy'
            ? Response::HTTP_OK
            : Response::HTTP_SERVICE_UNAVAILABLE;

        return response()->json($status, $httpStatus);
    }

    /**
     * Check Redis availability.
     */
    protected function checkRedis(): array
    {
        try {
            Redis::connection()->ping();

            return ['status' => 'healthy', 'message' => 'Connected'];
        } catch (\Throwable $e) {
            return [
                'status' => 'degraded',
                'message' => 'Unavailable - using database session and file cache fallback',
                'fallback_active' => true,
            ];
        }
    }

    /**
     * Check database connectivity.
     */
    protected function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');

            return ['status' => 'healthy', 'message' => 'Connected'];
        } catch (\Throwable $e) {
            return [
                'status' => 'critical',
                'message' => 'Database unreachable',
            ];
        }
    }

    /**
     * Check disk space availability.
     */
    protected function checkDisk(): array
    {
        $path = storage_path('app/public');

        if (! is_dir($path)) {
            $path = storage_path();
        }

        $freeSpace = @disk_free_space($path);

        if ($freeSpace === false) {
            return [
                'status' => 'degraded',
                'message' => 'Unable to determine free space',
            ];
        }

        if ($freeSpace < self::MIN_FREE_BYTES) {
            return [
                'status' => 'critical',
                'message' => 'Disk space critically low - uploads disabled',
                'free_bytes' => $freeSpace,
                'uploads_enabled' => false,
            ];
        }

        return [
            'status' => 'healthy',
            'message' => 'Sufficient space available',
            'free_bytes' => $freeSpace,
            'uploads_enabled' => true,
        ];
    }

    /**
     * Check email service health by examining failed jobs.
     */
    protected function checkEmail(): array
    {
        try {
            $recentFailures = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subHour())
                ->where('payload', 'like', '%Notification%')
                ->count();

            if ($recentFailures >= 5) {
                return [
                    'status' => 'degraded',
                    'message' => 'Email delivery experiencing failures - notifications queued for retry',
                    'recent_failures' => $recentFailures,
                    'in_app_fallback_active' => true,
                ];
            }

            return [
                'status' => 'healthy',
                'message' => 'Operating normally',
                'recent_failures' => $recentFailures,
            ];
        } catch (\Throwable $e) {
            // If we can't check failed_jobs table, still report as healthy
            return [
                'status' => 'healthy',
                'message' => 'Unable to check failure count',
            ];
        }
    }
}
