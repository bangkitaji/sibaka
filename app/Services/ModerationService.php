<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ModerationServiceInterface;
use App\Enums\ModerationAction;
use App\Enums\ReportReason;
use App\Models\Comment;
use App\Models\Content;
use App\Models\ModerationLog;
use App\Models\Reaction;
use App\Models\Report;
use App\Models\User;
use App\Models\Warning;
use App\Notifications\AccountSuspended;
use App\Services\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ModerationService implements ModerationServiceInterface
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}
    /**
     * Report content with a reason and optional description (max 500 chars).
     */
    public function reportContent(string $contentId, string $reporterId, string $reason, ?string $description = null): void
    {
        // Validate description length
        if ($description !== null && mb_strlen($description) > 500) {
            $description = mb_substr($description, 0, 500);
        }

        // Validate reason is a valid enum value
        $reportReason = ReportReason::from($reason);

        Report::create([
            'content_id' => $contentId,
            'reporter_id' => $reporterId,
            'reason' => $reportReason,
            'description' => $description,
            'status' => 'pending',
        ]);
    }

    /**
     * Review a flagged report. Actions: 'remove' (soft-delete content), 'dismiss' (mark reviewed), 'warn' (issue warning to author).
     */
    public function reviewFlag(string $flagId, string $moderatorId, string $action, ?string $ip = null): void
    {
        $report = Report::findOrFail($flagId);
        $content = Content::findOrFail($report->content_id);

        DB::transaction(function () use ($report, $content, $moderatorId, $action, $ip) {
            switch ($action) {
                case 'remove':
                    // Soft-delete the content
                    $content->update(['status' => 'hidden']);
                    $content->delete();

                    // Mark report as reviewed
                    $report->update([
                        'status' => 'reviewed',
                        'reviewed_by' => $moderatorId,
                        'reviewed_at' => now(),
                    ]);

                    // Log the moderation action
                    ModerationLog::create([
                        'moderator_id' => $moderatorId,
                        'target_user_id' => $content->author_id,
                        'target_content_id' => $content->id,
                        'action' => ModerationAction::RemoveContent,
                        'reason' => "Content removed after report review. Report reason: {$report->reason->value}",
                        'created_at' => now(),
                    ]);

                    // Audit log for content deletion via moderation
                    if ($ip) {
                        $this->auditLogService->log(
                            $moderatorId,
                            'content_deleted',
                            $ip,
                            "content:{$content->id}",
                            ['report_id' => $report->id, 'reason' => $report->reason->value]
                        );
                    }
                    break;

                case 'dismiss':
                    // Mark report as dismissed
                    $report->update([
                        'status' => 'dismissed',
                        'reviewed_by' => $moderatorId,
                        'reviewed_at' => now(),
                    ]);

                    // Log the dismissal
                    ModerationLog::create([
                        'moderator_id' => $moderatorId,
                        'target_user_id' => null,
                        'target_content_id' => $content->id,
                        'action' => ModerationAction::Dismiss,
                        'reason' => 'Report dismissed after review.',
                        'created_at' => now(),
                    ]);

                    // Audit log for moderation review action
                    if ($ip) {
                        $this->auditLogService->log(
                            $moderatorId,
                            'moderation_action',
                            $ip,
                            "report:{$report->id}",
                            ['action' => 'dismiss', 'content_id' => $content->id]
                        );
                    }
                    break;

                case 'warn':
                    // Issue a warning to the content author
                    $this->issueWarning(
                        $content->author_id,
                        "Warning issued for content violation. Report reason: {$report->reason->value}",
                        $moderatorId,
                        $ip
                    );

                    // Mark report as reviewed
                    $report->update([
                        'status' => 'reviewed',
                        'reviewed_by' => $moderatorId,
                        'reviewed_at' => now(),
                    ]);
                    break;

                default:
                    throw new \InvalidArgumentException("Invalid review action: {$action}. Must be 'remove', 'dismiss', or 'warn'.");
            }
        });
    }

    /**
     * Suspend a user for a specified duration (1-30 days), with reason and email notification.
     */
    public function suspendUser(string $userId, int $days, string $reason, string $moderatorId, ?string $ip = null): void
    {
        // Enforce duration bounds
        $days = max(1, min(30, $days));

        $user = User::findOrFail($userId);

        DB::transaction(function () use ($user, $days, $reason, $moderatorId, $ip) {
            $user->update([
                'is_suspended' => true,
                'suspended_until' => now()->addDays($days),
            ]);

            // Log moderation action
            ModerationLog::create([
                'moderator_id' => $moderatorId,
                'target_user_id' => $user->id,
                'target_content_id' => null,
                'action' => ModerationAction::SuspendUser,
                'reason' => $reason,
                'created_at' => now(),
            ]);

            // Audit log for suspension
            if ($ip) {
                $this->auditLogService->log(
                    $moderatorId,
                    'user_suspended',
                    $ip,
                    "user:{$user->id}",
                    ['days' => $days, 'reason' => $reason]
                );
            }
        });

        // Send email notification (queued for delivery within 30 seconds)
        $user->notify(new AccountSuspended($days, $reason));
    }

    /**
     * Issue a warning to a user. If 3 warnings in 90 days, auto-suspend for 7 days.
     */
    public function issueWarning(string $userId, string $message, string $moderatorId, ?string $ip = null): void
    {
        $user = User::findOrFail($userId);

        DB::transaction(function () use ($user, $message, $moderatorId, $ip) {
            // Create the warning record
            Warning::create([
                'user_id' => $user->id,
                'issued_by' => $moderatorId,
                'message' => $message,
            ]);

            // Log moderation action
            ModerationLog::create([
                'moderator_id' => $moderatorId,
                'target_user_id' => $user->id,
                'target_content_id' => null,
                'action' => ModerationAction::IssueWarning,
                'reason' => $message,
                'created_at' => now(),
            ]);

            // Audit log for warning
            if ($ip) {
                $this->auditLogService->log(
                    $moderatorId,
                    'warning_issued',
                    $ip,
                    "user:{$user->id}",
                    ['message' => $message]
                );
            }

            // Check escalation: 3 warnings within 90 days triggers auto-suspension
            $recentWarningsCount = Warning::where('user_id', $user->id)
                ->where('created_at', '>=', now()->subDays(90))
                ->count();

            if ($recentWarningsCount >= 3) {
                $this->suspendUser(
                    $user->id,
                    7,
                    'Automatic 7-day suspension: accumulated 3 warnings within 90 days.',
                    $moderatorId,
                    $ip
                );
            }
        });
    }

    /**
     * Check content against auto-flagging patterns (swear words, spam, malicious links).
     */
    public function checkAutoFlag(string $content): array
    {
        $matches = [];

        // Predefined patterns for auto-flagging
        $patterns = [
            'spam' => [
                '/\b(buy now|click here|free money|act now|limited time|winner|congratulations you won)\b/i',
                '/https?:\/\/(?:bit\.ly|tinyurl\.com|t\.co)\/[a-zA-Z0-9]+/i',
            ],
            'malicious_links' => [
                '/https?:\/\/[^\s]*\.(exe|bat|cmd|scr|pif|vbs|js)(\s|$)/i',
                '/javascript:/i',
                '/data:text\/html/i',
            ],
            'inappropriate' => [
                // Placeholder patterns - in production these would be more comprehensive
                '/\b(spam|phishing|scam)\b/i',
            ],
        ];

        foreach ($patterns as $category => $categoryPatterns) {
            foreach ($categoryPatterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $matches[] = $category;
                    break; // Only report each category once
                }
            }
        }

        return array_unique($matches);
    }

    /**
     * Get dashboard statistics: content volume, engagement, moderation metrics.
     */
    public function getDashboardStats(): array
    {
        return [
            'total_posts' => Content::count(),
            'active_users' => User::where('last_login_at', '>=', now()->subDays(30))->count(),
            'total_reactions' => Reaction::count(),
            'total_comments' => Comment::count(),
            'pending_reports' => Report::where('status', 'pending')->count(),
            'active_suspensions' => User::where('is_suspended', true)
                ->where('suspended_until', '>', now())
                ->count(),
            'warnings_issued' => Warning::where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }

    /**
     * Get moderation queue ordered by priority.
     * Includes content with pending reports AND auto-flagged content (pending_review status).
     * Content with 3+ reports appears first, then ordered by oldest report timestamp.
     */
    public function getModerationQueue(array $filters, int $page = 1): LengthAwarePaginator
    {
        $query = Content::query()
            ->withCount(['reports' => function ($query) {
                $query->where('status', 'pending');
            }])
            ->where(function ($q) {
                // Include content with pending reports
                $q->whereHas('reports', function ($query) {
                    $query->where('status', 'pending');
                })
                // Also include auto-flagged content with pending_review status
                ->orWhere('status', \App\Enums\ContentStatus::PendingReview);
            })
            ->with(['author', 'reports' => function ($query) {
                $query->where('status', 'pending')->oldest();
            }]);

        // Apply optional filters
        if (!empty($filters['reason'])) {
            $query->whereHas('reports', function ($q) use ($filters) {
                $q->where('status', 'pending')
                    ->where('reason', $filters['reason']);
            });
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        // Order: 3+ reports first (high priority), then by oldest pending report timestamp
        $query->orderByRaw('CASE WHEN (SELECT COUNT(*) FROM reports WHERE reports.content_id = contents.id AND reports.status = ?) >= 3 THEN 0 ELSE 1 END ASC', ['pending'])
            ->orderBy(
                Report::select('created_at')
                    ->whereColumn('reports.content_id', 'contents.id')
                    ->where('status', 'pending')
                    ->orderBy('created_at', 'asc')
                    ->limit(1),
                'asc'
            );

        return $query->paginate(15, ['*'], 'page', $page);
    }
}
