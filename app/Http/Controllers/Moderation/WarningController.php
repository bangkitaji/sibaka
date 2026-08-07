<?php

declare(strict_types=1);

namespace App\Http\Controllers\Moderation;

use App\Contracts\ModerationServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarningController extends Controller
{
    public function __construct(
        protected ModerationServiceInterface $moderationService
    ) {}

    /**
     * Issue a warning to a user.
     * POST /api/v1/moderation/warn
     *
     * Authorization is handled by 'can:moderate' route middleware.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'message' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        $userId = $request->input('user_id');
        $message = $request->input('message');
        $moderatorId = $request->user()->id;
        $ip = $request->ip();

        // Prevent warning other moderators/admins
        $targetUser = User::findOrFail($userId);
        if ($targetUser->isModerator()) {
            return response()->json([
                'message' => 'Cannot issue warnings to moderators or admins.',
            ], 403);
        }

        $this->moderationService->issueWarning($userId, $message, $moderatorId, $ip);

        return response()->json([
            'message' => 'Warning issued successfully.',
        ], 201);
    }
}
