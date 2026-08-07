<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ReactionServiceInterface;
use App\Enums\ReactionType;
use App\Models\Content;
use App\Models\Reaction;
use Illuminate\Database\UniqueConstraintViolationException;

class ReactionService implements ReactionServiceInterface
{
    /**
     * Add a reaction to content. Enforces one reaction per user per content.
     */
    public function react(string $contentId, string $userId, string $type): void
    {
        // Validate content exists
        Content::findOrFail($contentId);

        // Validate reaction type
        $reactionType = ReactionType::from($type);

        Reaction::create([
            'content_id' => $contentId,
            'user_id' => $userId,
            'type' => $reactionType,
        ]);
    }

    /**
     * Remove a user's reaction from content.
     */
    public function removeReaction(string $contentId, string $userId): void
    {
        Reaction::where('content_id', $contentId)
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * Change an existing reaction type. Does not change total count.
     */
    public function changeReaction(string $contentId, string $userId, string $newType): void
    {
        $reactionType = ReactionType::from($newType);

        $reaction = Reaction::where('content_id', $contentId)
            ->where('user_id', $userId)
            ->first();

        if ($reaction) {
            $reaction->update(['type' => $reactionType]);
        } else {
            // If no existing reaction, create one
            $this->react($contentId, $userId, $newType);
        }
    }

    /**
     * Get reaction summary with type breakdown, thresholds, and optional user reaction.
     */
    public function getReactionSummary(string $contentId, ?string $userId = null): array
    {
        $reactions = Reaction::where('content_id', $contentId)->get();

        $insightful = $reactions->where('type', ReactionType::Insightful)->count();
        $relatable = $reactions->where('type', ReactionType::Relatable)->count();
        $helpful = $reactions->where('type', ReactionType::Helpful)->count();
        $solutif = $reactions->where('type', ReactionType::Solutif)->count();
        $total = $reactions->count();

        $summary = [
            'total' => $total,
            'insightful' => $insightful,
            'relatable' => $relatable,
            'helpful' => $helpful,
            'solutif' => $solutif,
            'show_breakdown' => $total >= 50,
            'is_solutif_recommendation' => $solutif >= 10,
            'user_reaction' => null,
        ];

        if ($userId) {
            $userReaction = $reactions->where('user_id', $userId)->first();
            $summary['user_reaction'] = $userReaction?->type?->value;
        }

        return $summary;
    }
}
