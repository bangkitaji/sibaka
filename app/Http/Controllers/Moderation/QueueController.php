<?php

declare(strict_types=1);

namespace App\Http\Controllers\Moderation;

use App\Contracts\ModerationServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class QueueController extends Controller
{
    public function __construct(
        protected ModerationServiceInterface $moderationService
    ) {}

    /**
     * Get the moderation queue (priority ordered).
     *
     * Web route (Inertia): renders Moderation/Queue page with queue prop.
     * API route: returns JSON response.
     *
     * Authorization is handled by 'can:moderate' route middleware.
     */
    public function index(Request $request): JsonResponse|InertiaResponse
    {
        $filters = $request->only(['reason', 'category']);
        $page = (int) $request->input('page', 1);

        $queue = $this->moderationService->getModerationQueue($filters, $page);

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'data' => $queue->items(),
                'meta' => [
                    'current_page' => $queue->currentPage(),
                    'last_page' => $queue->lastPage(),
                    'per_page' => $queue->perPage(),
                    'total' => $queue->total(),
                ],
            ]);
        }

        return Inertia::render('Moderation/Queue', [
            'queue' => [
                'data' => $queue->items(),
                'meta' => [
                    'current_page' => $queue->currentPage(),
                    'last_page' => $queue->lastPage(),
                    'per_page' => $queue->perPage(),
                    'total' => $queue->total(),
                ],
            ],
        ]);
    }

    /**
     * Review a flagged report (take moderation action).
     * POST /moderation/flags/{report}
     *
     * Authorization is handled by 'can:moderate' route middleware.
     */
    public function review(Request $request, string $report): JsonResponse|RedirectResponse
    {
        $request->validate([
            'action' => ['required', 'in:remove,dismiss,warn'],
        ]);

        Report::findOrFail($report);

        $moderatorId = $request->user()->id;
        $action = $request->input('action');
        $ip = $request->ip();

        $this->moderationService->reviewFlag($report, $moderatorId, $action, $ip);

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Report reviewed successfully.',
            ]);
        }

        return redirect()
            ->route('moderation.queue')
            ->with('success', 'Report reviewed successfully.');
    }
}
