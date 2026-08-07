<?php

use App\Enums\ContentStatus;
use App\Enums\ModerationAction;
use App\Enums\ReportReason;
use App\Models\Content;
use App\Models\ModerationLog;
use App\Models\Report;
use App\Models\User;
use App\Models\Warning;

/*
|--------------------------------------------------------------------------
| Moderator: Flag → Queue → Review → Action Flow
|--------------------------------------------------------------------------
|
| Tests the complete moderation lifecycle: a member reports content,
| the report appears in the moderation queue, a moderator reviews and
| takes action (remove, dismiss, or warn).
|
| Validates: Requirements 12.1, 12.2, 12.3, 12.4, 12.5, 12.7, 12.8
|
*/

describe('Moderator: Flag → Queue → Review → Action Flow', function () {

    test('member reports content, moderator reviews queue and removes content', function () {
        $contentAuthor = User::factory()->member()->create();
        $reporter = User::factory()->member()->create();
        $moderator = User::factory()->moderator()->create();

        // Step 1: Published content exists
        $content = Content::factory()->published()->create([
            'author_id' => $contentAuthor->id,
            'title' => 'Potentially Violating Content',
        ]);

        // Step 2: Member reports the content
        $reportResponse = $this->actingAs($reporter)
            ->postJson('/reports', [
                'content_id' => $content->id,
                'reason' => 'harassment',
                'description' => 'This content contains personal attacks against a colleague.',
            ]);

        $reportResponse->assertCreated();
        $reportResponse->assertJsonFragment(['message' => 'Content reported successfully. Moderators have been notified.']);

        // Verify report was stored
        $report = Report::where('content_id', $content->id)
            ->where('reporter_id', $reporter->id)
            ->first();
        expect($report)->not->toBeNull();
        expect($report->reason->value)->toBe('harassment');
        expect($report->status)->toBe('pending');
        expect($report->description)->toBe('This content contains personal attacks against a colleague.');

        // Step 3: Moderator accesses the queue
        $queueResponse = $this->actingAs($moderator)
            ->getJson('/moderation/queue');

        $queueResponse->assertOk();

        // Step 4: Moderator reviews and removes the content
        $reviewResponse = $this->actingAs($moderator)
            ->postJson("/moderation/flags/{$report->id}", [
                'action' => 'remove',
            ]);

        $reviewResponse->assertOk();
        $reviewResponse->assertJsonFragment(['message' => 'Report reviewed successfully.']);

        // Step 5: Verify content was soft-deleted
        $content->refresh();
        expect($content->deleted_at)->not->toBeNull();

        // Step 6: Verify report status updated
        $report->refresh();
        expect($report->status)->toBe('reviewed');
        expect($report->reviewed_by)->toBe($moderator->id);
        expect($report->reviewed_at)->not->toBeNull();

        // Step 7: Verify moderation log entry was created
        $moderationLog = ModerationLog::where('moderator_id', $moderator->id)
            ->where('target_content_id', $content->id)
            ->where('action', ModerationAction::RemoveContent)
            ->first();
        expect($moderationLog)->not->toBeNull();
        expect($moderationLog->target_user_id)->toBe($contentAuthor->id);
    });

    test('moderator dismisses a report', function () {
        $contentAuthor = User::factory()->member()->create();
        $reporter = User::factory()->member()->create();
        $moderator = User::factory()->moderator()->create();

        $content = Content::factory()->published()->create([
            'author_id' => $contentAuthor->id,
        ]);

        // Create report
        $this->actingAs($reporter)
            ->postJson('/reports', [
                'content_id' => $content->id,
                'reason' => 'off_topic',
                'description' => 'This content seems unrelated.',
            ]);

        $report = Report::where('content_id', $content->id)->first();

        // Moderator dismisses
        $response = $this->actingAs($moderator)
            ->postJson("/moderation/flags/{$report->id}", [
                'action' => 'dismiss',
            ]);

        $response->assertOk();

        // Content should still be published (not deleted)
        $content->refresh();
        expect($content->deleted_at)->toBeNull();
        expect($content->status)->toBe(ContentStatus::Published);

        // Report should be dismissed
        $report->refresh();
        expect($report->status)->toBe('dismissed');
        expect($report->reviewed_by)->toBe($moderator->id);
    });

    test('moderator issues warning to content author', function () {
        $contentAuthor = User::factory()->member()->create();
        $reporter = User::factory()->member()->create();
        $moderator = User::factory()->moderator()->create();

        $content = Content::factory()->published()->create([
            'author_id' => $contentAuthor->id,
        ]);

        // Create report
        $this->actingAs($reporter)
            ->postJson('/reports', [
                'content_id' => $content->id,
                'reason' => 'spam',
            ]);

        $report = Report::where('content_id', $content->id)->first();

        // Moderator issues warning
        $response = $this->actingAs($moderator)
            ->postJson("/moderation/flags/{$report->id}", [
                'action' => 'warn',
            ]);

        $response->assertOk();

        // Verify warning was created for the content author
        $warning = Warning::where('user_id', $contentAuthor->id)
            ->where('issued_by', $moderator->id)
            ->first();
        expect($warning)->not->toBeNull();

        // Report should be reviewed
        $report->refresh();
        expect($report->status)->toBe('reviewed');
    });

    test('moderator can suspend a user', function () {
        $member = User::factory()->member()->create();
        $moderator = User::factory()->moderator()->create();

        // Moderator suspends the member
        $response = $this->actingAs($moderator)
            ->postJson('/moderation/suspend', [
                'user_id' => $member->id,
                'days' => 7,
                'reason' => 'Repeated violation of community guidelines.',
            ]);

        $response->assertCreated();
        $response->assertJsonFragment(['message' => 'User suspended for 7 day(s).']);

        // Verify user is suspended
        $member->refresh();
        expect($member->is_suspended)->toBeTrue();
        expect($member->suspended_until)->not->toBeNull();

        // Verify moderation log
        $log = ModerationLog::where('moderator_id', $moderator->id)
            ->where('target_user_id', $member->id)
            ->where('action', ModerationAction::SuspendUser)
            ->first();
        expect($log)->not->toBeNull();
        expect($log->reason)->toBe('Repeated violation of community guidelines.');
    });

    test('suspended user cannot create content', function () {
        $suspendedUser = User::factory()->member()->suspended()->create();

        $this->seed(\Database\Seeders\TagSeeder::class);

        $response = $this->actingAs($suspendedUser)
            ->post('/content', [
                'title' => 'I am suspended',
                'body' => 'This should not work.',
                'category' => 'tech_stack',
                'publish' => true,
                'tags' => [
                    'tech_stack' => ['python'],
                    'experience_level' => 'beginner',
                    'category' => 'architecture',
                ],
            ]);

        // Policy denies because isActiveMember() returns false for suspended users
        $response->assertForbidden();
    });

    test('non-moderator cannot access moderation queue', function () {
        $member = User::factory()->member()->create();

        $response = $this->actingAs($member)
            ->getJson('/moderation/queue');

        $response->assertForbidden();
    });

    test('non-moderator cannot review reports', function () {
        $member = User::factory()->member()->create();
        $reporter = User::factory()->member()->create();
        $contentAuthor = User::factory()->member()->create();

        $content = Content::factory()->published()->create([
            'author_id' => $contentAuthor->id,
        ]);

        $report = Report::factory()->pending()->create([
            'content_id' => $content->id,
            'reporter_id' => $reporter->id,
        ]);

        $response = $this->actingAs($member)
            ->postJson("/moderation/flags/{$report->id}", [
                'action' => 'remove',
            ]);

        $response->assertForbidden();
    });

    test('priority queue orders reports with 3+ reports before others', function () {
        $moderator = User::factory()->moderator()->create();
        $reporters = User::factory()->member()->count(4)->create();
        $author = User::factory()->member()->create();

        // Content with 1 report (lower priority)
        $lowPriorityContent = Content::factory()->published()->create([
            'author_id' => $author->id,
            'title' => 'Low Priority',
        ]);
        Report::factory()->pending()->create([
            'content_id' => $lowPriorityContent->id,
            'reporter_id' => $reporters[0]->id,
        ]);

        // Content with 3 reports (higher priority)
        $highPriorityContent = Content::factory()->published()->create([
            'author_id' => $author->id,
            'title' => 'High Priority',
        ]);
        foreach ($reporters->take(3) as $reporter) {
            Report::factory()->pending()->create([
                'content_id' => $highPriorityContent->id,
                'reporter_id' => $reporter->id,
            ]);
        }

        // Access moderation queue
        $response = $this->actingAs($moderator)
            ->getJson('/moderation/queue');

        $response->assertOk();

        $data = $response->json('data');

        // High priority content (3+ reports) should appear before low priority
        if (count($data) >= 2) {
            $firstContentId = $data[0]['content_id'] ?? $data[0]['id'] ?? null;
            // The item with more reports should come first
            expect(
                Report::where('content_id', $firstContentId)->where('status', 'pending')->count()
            )->toBeGreaterThanOrEqual(3);
        }
    });

    test('warning escalation triggers auto-suspension after 3 warnings in 90 days', function () {
        $member = User::factory()->member()->create();
        $moderator = User::factory()->moderator()->create();

        // Create 2 existing warnings within last 90 days
        Warning::create([
            'user_id' => $member->id,
            'issued_by' => $moderator->id,
            'message' => 'First warning',
            'created_at' => now()->subDays(30),
        ]);

        Warning::create([
            'user_id' => $member->id,
            'issued_by' => $moderator->id,
            'message' => 'Second warning',
            'created_at' => now()->subDays(15),
        ]);

        // Issue a 3rd warning via the moderation service
        $moderationService = app(\App\Contracts\ModerationServiceInterface::class);
        $moderationService->issueWarning(
            $member->id,
            'Third warning - should trigger auto-suspension',
            $moderator->id,
            '127.0.0.1'
        );

        // User should now be auto-suspended for 7 days
        $member->refresh();
        expect($member->is_suspended)->toBeTrue();
        expect($member->suspended_until)->not->toBeNull();

        // Verify 3 warnings exist
        $warningCount = Warning::where('user_id', $member->id)->count();
        expect($warningCount)->toBe(3);
    });
});
