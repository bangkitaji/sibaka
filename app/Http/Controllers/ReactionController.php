<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\ReactionServiceInterface;
use App\Enums\ReactionType;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class ReactionController extends Controller
{
    public function __construct(
        protected ReactionServiceInterface $reactionService
    ) {}

    /**
     * Add or change a reaction on content.
     * POST /content/{content}/reactions
     */
    public function store(Request $request, string $content): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'string', new Enum(ReactionType::class)],
        ]);

        $userId = $request->user()->id;
        $type = $request->input('type');

        try {
            $this->reactionService->react($content, $userId, $type);
        } catch (UniqueConstraintViolationException $e) {
            // User already has a reaction — change it
            $this->reactionService->changeReaction($content, $userId, $type);
        }

        $summary = $this->reactionService->getReactionSummary($content, $userId);

        return response()->json([
            'data' => $summary,
            'message' => 'Reaction saved.',
        ]);
    }

    /**
     * Remove a reaction from content.
     * DELETE /content/{content}/reactions
     */
    public function destroy(Request $request, string $content): JsonResponse
    {
        $userId = $request->user()->id;

        $this->reactionService->removeReaction($content, $userId);

        $summary = $this->reactionService->getReactionSummary($content, $userId);

        return response()->json([
            'data' => $summary,
            'message' => 'Reaction removed.',
        ]);
    }
}
