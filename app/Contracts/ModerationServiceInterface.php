<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ModerationServiceInterface
{
    /**
     * Report content with a reason and optional description.
     *
     * @param string $contentId The content being reported
     * @param string $reporterId The member submitting the report
     * @param string $reason Report reason (spam, harassment, misinformation, off_topic, other)
     * @param string|null $description Optional description (max 500 characters)
     */
    public function reportContent(string $contentId, string $reporterId, string $reason, ?string $description = null): void;

    /**
     * Review a flagged report - moderator takes action.
     *
     * @param string $flagId The report ID being reviewed
     * @param string $moderatorId The moderator performing the review
     * @param string $action Action to take: 'remove', 'dismiss', or 'warn'
     * @param string|null $ip IP address for audit logging
     */
    public function reviewFlag(string $flagId, string $moderatorId, string $action, ?string $ip = null): void;

    /**
     * Suspend a user for a specified duration.
     *
     * @param string $userId The user to suspend
     * @param int $days Duration in days (1-30)
     * @param string $reason Reason for suspension
     * @param string $moderatorId The moderator performing the suspension
     * @param string|null $ip IP address for audit logging
     */
    public function suspendUser(string $userId, int $days, string $reason, string $moderatorId, ?string $ip = null): void;

    /**
     * Issue a warning to a user. Checks escalation (3 warnings in 90 days = 7-day auto-suspension).
     *
     * @param string $userId The user receiving the warning
     * @param string $message Warning message
     * @param string $moderatorId The moderator issuing the warning
     * @param string|null $ip IP address for audit logging
     */
    public function issueWarning(string $userId, string $message, string $moderatorId, ?string $ip = null): void;

    /**
     * Check content against auto-flagging patterns.
     *
     * @param string $content The content text to check
     * @return array Matched patterns (empty if no matches)
     */
    public function checkAutoFlag(string $content): array;

    /**
     * Get dashboard statistics for the moderation panel.
     *
     * @return array Stats including total_posts, active_users, total_reactions, total_comments, pending_reports, active_suspensions, warnings_issued
     */
    public function getDashboardStats(): array;

    /**
     * Get the moderation queue ordered by priority.
     * Items with 3+ reports appear first, then ordered by oldest report timestamp.
     *
     * @param array $filters Optional filters for the queue
     * @param int $page Page number for pagination
     * @return LengthAwarePaginator
     */
    public function getModerationQueue(array $filters, int $page = 1): LengthAwarePaginator;
}
