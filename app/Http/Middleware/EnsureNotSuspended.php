<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotSuspended
{
    /**
     * Handle an incoming request.
     *
     * Ensure that the authenticated user is not currently suspended.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Check if suspension has expired
        if ($user->is_suspended && $user->suspended_until && $user->suspended_until->isPast()) {
            $user->update([
                'is_suspended' => false,
                'suspended_until' => null,
            ]);

            return $next($request);
        }

        if ($user->is_suspended) {
            $suspensionInfo = [
                'message' => 'Your account is currently suspended.',
                'suspended_until' => $user->suspended_until?->toIso8601String(),
            ];

            if ($request->expectsJson()) {
                return response()->json($suspensionInfo, 403);
            }

            abort(403, 'Your account is currently suspended until ' . ($user->suspended_until?->format('Y-m-d H:i') ?? 'further notice') . '.');
        }

        return $next($request);
    }
}
