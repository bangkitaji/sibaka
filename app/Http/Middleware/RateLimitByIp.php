<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitByIp
{
    /**
     * Handle an incoming request.
     *
     * Enforces a per-IP rate limit (default 100 requests/minute).
     * When the limit is exceeded, returns a 429 response with a CAPTCHA challenge indicator.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = 'ip_rate_limit:' . $request->ip();
        $maxAttempts = config('sibaka.rate_limit_per_minute', 100);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return $this->buildRateLimitResponse($key, $maxAttempts);
        }

        RateLimiter::hit($key, 60);

        $response = $next($request);

        return $this->addRateLimitHeaders($response, $key, $maxAttempts);
    }

    /**
     * Build the 429 rate limit exceeded response with CAPTCHA challenge.
     */
    protected function buildRateLimitResponse(string $key, int $maxAttempts): Response
    {
        $retryAfter = RateLimiter::availableIn($key);

        return response()->json([
            'message' => 'Too many requests. Please complete the CAPTCHA challenge to continue.',
            'captcha_required' => true,
            'retry_after' => $retryAfter,
        ], 429, [
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
        ]);
    }

    /**
     * Add rate limit headers to the response.
     */
    protected function addRateLimitHeaders(Response $response, string $key, int $maxAttempts): Response
    {
        $remaining = RateLimiter::remaining($key, $maxAttempts);

        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);

        return $response;
    }
}
