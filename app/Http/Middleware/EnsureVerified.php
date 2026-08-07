<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\VerificationStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVerified
{
    /**
     * Handle an incoming request.
     *
     * Ensure that the authenticated user has an approved verification status.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->verification_status !== VerificationStatus::Approved) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your account is not yet verified.',
                ], 403);
            }

            return redirect()->route('verification.pending');
        }

        return $next($request);
    }
}
