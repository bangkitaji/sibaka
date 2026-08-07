<?php

declare(strict_types=1);

namespace App\Contracts;

interface AnonymityServiceInterface
{
    /**
     * Store anonymous metadata for content, stripping author identity from public view.
     *
     * @param string $contentId The content being published anonymously
     * @param string $authorId The actual author (stored internally for moderation)
     * @param array $metadata Technical data: ip_hash (SHA256), browser_fingerprint, user_agent
     */
    public function publishAnonymously(string $contentId, string $authorId, array $metadata): void;

    /**
     * Check if the author can publish anonymously (rate limit: 5 posts per 24 hours).
     */
    public function canPublishAnonymously(string $authorId): bool;

    /**
     * Return the author ID for moderation purposes (moderator/admin only).
     *
     * @throws \App\Exceptions\AnonymityException If moderator lacks permission
     */
    public function getAuthorForModeration(string $contentId, string $moderatorId): string;

    /**
     * Purge anonymous metadata records older than 90 days.
     *
     * @return int Number of records deleted
     */
    public function purgeExpiredMetadata(): int;
}
