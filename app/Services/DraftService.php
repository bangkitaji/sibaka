<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Draft;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class DraftService
{
    /**
     * TTL for draft keys in Redis (7 days in seconds).
     */
    private const TTL_SECONDS = 604800;

    /**
     * Redis key prefix for drafts.
     */
    private const KEY_PREFIX = 'draft:';

    /**
     * Save draft body to Redis (primary) with DB fallback.
     */
    public function save(string $contentId, string $authorId, string $body): void
    {
        try {
            Redis::setex(
                self::KEY_PREFIX . $contentId,
                self::TTL_SECONDS,
                $body
            );
        } catch (\Throwable $e) {
            Log::warning('Redis unavailable for draft save, falling back to DB', [
                'content_id' => $contentId,
                'error' => $e->getMessage(),
            ]);

            $this->saveToDB($contentId, $authorId, $body);
        }
    }

    /**
     * Restore draft body from Redis (primary) with DB fallback.
     */
    public function restore(string $contentId): ?string
    {
        try {
            $body = Redis::get(self::KEY_PREFIX . $contentId);

            if ($body !== null && $body !== false) {
                return (string) $body;
            }
        } catch (\Throwable $e) {
            Log::warning('Redis unavailable for draft restore, falling back to DB', [
                'content_id' => $contentId,
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback: try database
        return $this->restoreFromDB($contentId);
    }

    /**
     * Delete draft from both Redis and DB.
     */
    public function delete(string $contentId): void
    {
        try {
            Redis::del(self::KEY_PREFIX . $contentId);
        } catch (\Throwable $e) {
            Log::warning('Redis unavailable for draft delete', [
                'content_id' => $contentId,
                'error' => $e->getMessage(),
            ]);
        }

        Draft::where('content_id', $contentId)->delete();
    }

    /**
     * Fallback: save draft to database.
     */
    private function saveToDB(string $contentId, string $authorId, string $body): void
    {
        Draft::updateOrCreate(
            ['content_id' => $contentId],
            [
                'author_id' => $authorId,
                'body' => $body,
                'saved_at' => now(),
            ]
        );
    }

    /**
     * Fallback: restore draft from database.
     */
    private function restoreFromDB(string $contentId): ?string
    {
        $draft = Draft::where('content_id', $contentId)->first();

        return $draft?->body;
    }
}
