<?php

declare(strict_types=1);

namespace App\Contracts;

interface ReactionServiceInterface
{
    /**
     * Add a reaction to content. One reaction per user per content (unique constraint).
     *
     * @param string $contentId The content being reacted to
     * @param string $userId The user adding the reaction
     * @param string $type The reaction type (insightful, relatable, helpful, solutif)
     *
     * @throws \Illuminate\Database\UniqueConstraintViolationException If user already reacted
     */
    public function react(string $contentId, string $userId, string $type): void;

    /**
     * Remove a user's reaction from content.
     *
     * @param string $contentId The content to remove reaction from
     * @param string $userId The user removing their reaction
     */
    public function removeReaction(string $contentId, string $userId): void;

    /**
     * Change an existing reaction's type (doesn't change total count).
     *
     * @param string $contentId The content with the reaction
     * @param string $userId The user changing their reaction
     * @param string $newType The new reaction type
     */
    public function changeReaction(string $contentId, string $userId, string $newType): void;

    /**
     * Get reaction summary for a content item.
     *
     * Returns:
     * - total: total reaction count
     * - insightful: count of insightful reactions
     * - relatable: count of relatable reactions
     * - helpful: count of helpful reactions
     * - solutif: count of solutif reactions
     * - show_breakdown: true if total >= 50
     * - is_solutif_recommendation: true if solutif count >= 10
     * - user_reaction: the authenticated user's reaction type (null if none or not authenticated)
     *
     * @param string $contentId The content to get summary for
     * @param string|null $userId Optional user ID to include their reaction in summary
     * @return array Reaction summary with breakdown and badges
     */
    public function getReactionSummary(string $contentId, ?string $userId = null): array;
}
