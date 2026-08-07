<?php

declare(strict_types=1);

namespace App\Http\Controllers\Moderation;

use App\Contracts\ModerationServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function __construct(
        protected ModerationServiceInterface $moderationService
    ) {}

    /**
     * Get moderation dashboard statistics.
     *
     * Web route (Inertia): renders Moderation/Dashboard page with stats prop.
     * API route: returns JSON response.
     *
     * Authorization is handled by 'can:moderate' route middleware.
     */
    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $stats = $this->moderationService->getDashboardStats();

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'data' => $stats,
            ]);
        }

        return Inertia::render('Moderation/Dashboard', [
            'stats' => $stats,
        ]);
    }
}
