<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Inertia middleware appended to the web group for shared props
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // Route middleware aliases for use in route definitions:
        //   auth       → Laravel's built-in auth guard (Sanctum session-based for SPA)
        //   verified   → ensures user has approved verification status
        //   not-suspended → ensures user is not currently suspended
        //   rate-limit-ip → per-IP rate limiting (100 req/min, CAPTCHA on exceed)
        //   check-disk-space → rejects file uploads when disk is full (507)
        //
        // Intended route-level stacking order:
        //   auth → verified → not-suspended → rate-limit-ip
        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureVerified::class,
            'not-suspended' => \App\Http\Middleware\EnsureNotSuspended::class,
            'rate-limit-ip' => \App\Http\Middleware\RateLimitByIp::class,
            'check-disk-space' => \App\Http\Middleware\CheckDiskSpace::class,
        ]);

        // Enable Sanctum's stateful middleware on the API group
        // so cookie-based SPA authentication works for Inertia requests
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle custom infrastructure exceptions (disk full, service unavailable)
        $exceptions->renderable(function (\App\Exceptions\InfrastructureException $e, Request $request) {
            $statusCode = $e->getCode() ?: Response::HTTP_SERVICE_UNAVAILABLE;

            if ($request->expectsJson() || $request->header('X-Inertia')) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], $statusCode);
            }

            return response($e->getMessage(), $statusCode);
        });

        // Handle filesystem full errors from file write operations
        $exceptions->renderable(function (\ErrorException $e, Request $request) {
            if (str_contains($e->getMessage(), 'No space left on device')
                || str_contains($e->getMessage(), 'disk quota exceeded')
                || str_contains($e->getMessage(), 'There is not enough space on the disk')) {
                $message = 'Storage capacity is temporarily full. File uploads are currently disabled. Please try again later.';

                \Illuminate\Support\Facades\Log::error('Filesystem full detected via write error', [
                    'original_error' => $e->getMessage(),
                ]);

                if ($request->expectsJson() || $request->header('X-Inertia')) {
                    return response()->json([
                        'message' => $message,
                    ], Response::HTTP_INSUFFICIENT_STORAGE);
                }

                return response($message, Response::HTTP_INSUFFICIENT_STORAGE);
            }

            return null;
        });

        // Handle email transport failures gracefully - don't interrupt user flow
        $exceptions->renderable(function (\Symfony\Component\Mailer\Exception\TransportException $e, Request $request) {
            \Illuminate\Support\Facades\Log::warning('Email service unavailable, notification queued for retry', [
                'error' => $e->getMessage(),
            ]);

            // Return null to let the default handler decide; for queued jobs,
            // Laravel's retry mechanism will handle redelivery automatically.
            return null;
        });
    })->create();
