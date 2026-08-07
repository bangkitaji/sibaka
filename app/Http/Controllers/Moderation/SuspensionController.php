<?php

declare(strict_types=1);

namespace App\Http\Controllers\Moderation;

use App\Contracts\ModerationServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuspensionController extends Controller
{
    public function __construct(
        protected ModerationServiceInterface $moderationService
    ) {}

    /**
     * Suspend a user account.
     * POST /api/v1/moderation/suspend
     *
     * Authorization is handled by 'can:moderate' route middleware.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'days' => ['required', 'integer', 'min:1', 'max:30'],
            'reason' => ['required', 'string', 'min:1', 'max:500'],
        ]);

        $userId = $request->input('user_id');
        $days = (int) $request->input('days');
        $reason = $request->input('reason');
        $moderatorId = $request->user()->id;
        $ip = $request->ip();

        // Prevent suspending other moderators/admins
        $targetUser = User::findOrFail($userId);
        if ($targetUser->isModerator()) {
            return response()->json([
                'message' => 'Cannot suspend moderators or admins.',
            ], 403);
        }

        $this->moderationService->suspendUser($userId, $days, $reason, $moderatorId, $ip);

        return response()->json([
            'message' => "User suspended for {$days} day(s).",
        ], 201);
    }
}
