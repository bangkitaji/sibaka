<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ContentStatus;
use App\Enums\ReportReason;
use App\Models\Content;
use App\Models\Report;
use App\Services\ModerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoFlagContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly string $contentId
    ) {}

    /**
     * Execute the job.
     *
     * Checks content against auto-flagging patterns (swear words, spam, malicious links).
     * If patterns match, sets content status to 'pending_review' and creates a Report.
     */
    public function handle(ModerationService $moderationService): void
    {
        $content = Content::find($this->contentId);

        if (!$content) {
            Log::warning('AutoFlagContent: Content not found.', ['content_id' => $this->contentId]);
            return;
        }

        // Only check published content (skip drafts, already flagged, etc.)
        if (!in_array($content->status, [ContentStatus::Published, ContentStatus::PendingReview])) {
            return;
        }

        // Check the content body against auto-flag patterns
        $textToCheck = $content->title . ' ' . $content->body;
        $matchedCategories = $moderationService->checkAutoFlag($textToCheck);

        if (empty($matchedCategories)) {
            return;
        }

        DB::transaction(function () use ($content, $matchedCategories) {
            // Set content status to pending_review
            $content->update([
                'status' => ContentStatus::PendingReview,
            ]);

            // Create a report with auto_flagged reason
            Report::create([
                'content_id' => $content->id,
                'reporter_id' => null, // System-generated report
                'reason' => ReportReason::AutoFlagged,
                'description' => 'Auto-flagged for: ' . implode(', ', $matchedCategories),
                'status' => 'pending',
            ]);
        });

        Log::info('AutoFlagContent: Content auto-flagged.', [
            'content_id' => $this->contentId,
            'matched_categories' => $matchedCategories,
        ]);
    }
}
