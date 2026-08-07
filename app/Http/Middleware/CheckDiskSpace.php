<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that checks available disk space before allowing file uploads.
 *
 * When the filesystem is full (below minimum threshold), this middleware
 * returns a 507 Insufficient Storage response with a friendly message,
 * preventing uploads that would fail anyway.
 */
class CheckDiskSpace
{
    /**
     * Minimum free disk space in bytes (50 MB threshold).
     */
    private const MIN_FREE_BYTES = 52_428_800;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only check on requests that include file uploads
        if (! $request->hasFile('files') && ! $request->hasFile('file')) {
            return $next($request);
        }

        if (! $this->hasSufficientDiskSpace()) {
            Log::error('Disk space critically low - uploads disabled', [
                'free_space' => $this->getFreeSpace(),
                'storage_path' => storage_path('app/public'),
            ]);

            if ($request->expectsJson() || $request->header('X-Inertia')) {
                return response()->json([
                    'message' => 'Storage capacity is temporarily full. File uploads are currently disabled. Please try again later or contact an administrator.',
                ], Response::HTTP_INSUFFICIENT_STORAGE);
            }

            abort(Response::HTTP_INSUFFICIENT_STORAGE, 'Storage capacity is temporarily full. File uploads are currently disabled. Please try again later.');
        }

        return $next($request);
    }

    /**
     * Check if the storage disk has sufficient free space.
     */
    protected function hasSufficientDiskSpace(): bool
    {
        $freeSpace = $this->getFreeSpace();

        if ($freeSpace === false) {
            // If we can't determine disk space, allow the request
            // (the actual write will fail and be caught by the exception handler)
            return true;
        }

        return $freeSpace >= (float) self::MIN_FREE_BYTES;
    }

    /**
     * Get free disk space for the storage path.
     */
    protected function getFreeSpace(): float|false
    {
        $path = storage_path('app/public');

        if (! is_dir($path)) {
            $path = storage_path();
        }

        return @disk_free_space($path);
    }
}
