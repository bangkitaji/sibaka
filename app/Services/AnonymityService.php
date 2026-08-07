<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AnonymityServiceInterface;
use App\Exceptions\AnonymityException;
use App\Models\AnonymousMetadata;
use App\Models\User;

class AnonymityService implements AnonymityServiceInterface
{
    /**
     * Maximum anonymous posts allowed within a 24-hour window.
     */
    private const MAX_ANONYMOUS_POSTS_PER_DAY = 5;

    /**
     * Number of days before anonymous metadata expires.
     */
    private const METADATA_RETENTION_DAYS = 90;

    /**
     * Store anonymous metadata for content.
     *
     * Stores: content_id, author_id (internal), IP hash (SHA256),
     * browser_fingerprint, user_agent, and sets expires_at to 90 days.
     */
    public function publishAnonymously(string $contentId, string $authorId, array $metadata): void
    {
        AnonymousMetadata::create([
            'content_id' => $contentId,
            'author_id' => $authorId,
            'ip_hash' => $metadata['ip_hash'] ?? hash('sha256', $metadata['ip'] ?? ''),
            'browser_fingerprint' => $metadata['browser_fingerprint'] ?? '',
            'user_agent' => $metadata['user_agent'] ?? '',
            'expires_at' => now()->addDays(self::METADATA_RETENTION_DAYS),
        ]);
    }

    /**
     * Check if the author can publish anonymously.
     *
     * Returns true if fewer than 5 anonymous posts in last 24 hours.
     */
    public function canPublishAnonymously(string $authorId): bool
    {
        $count = AnonymousMetadata::where('author_id', $authorId)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        return $count < self::MAX_ANONYMOUS_POSTS_PER_DAY;
    }

    /**
     * Return the author ID for moderation purposes.
     *
     * Only moderators and admins can access this information.
     *
     * @throws AnonymityException If user is not a moderator/admin or metadata not found
     */
    public function getAuthorForModeration(string $contentId, string $moderatorId): string
    {
        $moderator = User::findOrFail($moderatorId);

        if (!$moderator->isModerator()) {
            throw AnonymityException::unauthorizedModeration();
        }

        $metadata = AnonymousMetadata::where('content_id', $contentId)->first();

        if (!$metadata) {
            throw AnonymityException::metadataNotFound();
        }

        return $metadata->author_id;
    }

    /**
     * Purge anonymous metadata records older than 90 days.
     *
     * @return int Number of records deleted
     */
    public function purgeExpiredMetadata(): int
    {
        return AnonymousMetadata::where('created_at', '<', now()->subDays(self::METADATA_RETENTION_DAYS))
            ->delete();
    }
}
