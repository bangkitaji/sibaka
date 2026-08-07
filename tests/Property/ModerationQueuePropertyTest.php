<?php

namespace Tests\Property;

use App\Models\Content;
use App\Models\Report;
use App\Models\User;
use App\Services\ModerationService;
use Carbon\Carbon;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property 28: Moderation Priority Queue Ordering
 *
 * For any set of flagged content items, the moderation queue SHALL order items
 * such that content with 3 or more pending reports appears before content with
 * fewer than 3 pending reports. Within each priority tier, items SHALL be ordered
 * by oldest pending report timestamp (ASC) — i.e., content reported earliest
 * appears first within each tier.
 *
 * Properties tested:
 * 1. All items with 3+ pending reports appear before items with fewer than 3
 * 2. Within the "3+ reports" tier, items are ordered by oldest pending report timestamp (ASC)
 * 3. Within the "< 3 reports" tier, items are also ordered by oldest pending report timestamp (ASC)
 *
 * **Validates: Requirements 12.1**
 */
class ModerationQueuePropertyTest extends TestCase
{
    use TestTrait;
    use RefreshDatabase;

    private ModerationService $moderationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->moderationService = app(ModerationService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Helper: create content with a specific number of pending reports at specific timestamps.
     *
     * @param User $author
     * @param int $pendingReportCount
     * @param Carbon $oldestReportTimestamp - the oldest pending report's created_at
     * @return Content
     */
    private function createContentWithReports(User $author, int $pendingReportCount, Carbon $oldestReportTimestamp): Content
    {
        $content = Content::factory()->published()->create([
            'author_id' => $author->id,
        ]);

        // Create pending reports with timestamps starting from the oldest
        for ($i = 0; $i < $pendingReportCount; $i++) {
            $reportTimestamp = $oldestReportTimestamp->copy()->addMinutes($i * 10);
            Report::factory()->pending()->create([
                'content_id' => $content->id,
                'reporter_id' => User::factory()->member()->create()->id,
                'created_at' => $reportTimestamp,
                'updated_at' => $reportTimestamp,
            ]);
        }

        return $content;
    }

    /**
     * Property: All items with 3+ pending reports appear before items with fewer than 3.
     *
     * Generate random sets of content with varied report counts (some with 3+, some with <3).
     * Verify that in the moderation queue, all high-priority items appear first.
     */
    public function testHighPriorityItemsAppearBeforeLowPriority(): void
    {
        $this->forAll(
            Generators::choose(1, 4), // number of high-priority items (3+ reports)
            Generators::choose(1, 4), // number of low-priority items (< 3 reports)
            Generators::choose(1, 10000) // seed for randomness
        )
            ->then(function (int $highCount, int $lowCount, int $seed) {
                Carbon::setTestNow(Carbon::now());

                $author = User::factory()->member()->create();

                $highPriorityIds = [];
                $lowPriorityIds = [];

                // Create high-priority content (3+ pending reports)
                for ($i = 0; $i < $highCount; $i++) {
                    $reportCount = 3 + (($seed * ($i + 1) * 7) % 5); // 3-7 reports
                    $baseTimestamp = now()->subDays(($seed * ($i + 1) * 3) % 30 + 1);

                    $content = $this->createContentWithReports($author, $reportCount, $baseTimestamp);
                    $highPriorityIds[] = $content->id;
                }

                // Create low-priority content (1-2 pending reports)
                for ($i = 0; $i < $lowCount; $i++) {
                    $reportCount = 1 + (($seed * ($i + 1) * 11) % 2); // 1-2 reports
                    $baseTimestamp = now()->subDays(($seed * ($i + 1) * 5) % 30 + 1);

                    $content = $this->createContentWithReports($author, $reportCount, $baseTimestamp);
                    $lowPriorityIds[] = $content->id;
                }

                // Get the moderation queue
                $queue = $this->moderationService->getModerationQueue([], 1);
                $queueIds = $queue->pluck('id')->toArray();

                // Find the positions of high and low priority items
                $highPositions = [];
                $lowPositions = [];

                foreach ($queueIds as $position => $id) {
                    if (in_array($id, $highPriorityIds)) {
                        $highPositions[] = $position;
                    }
                    if (in_array($id, $lowPriorityIds)) {
                        $lowPositions[] = $position;
                    }
                }

                // INVARIANT: All high-priority positions must be before all low-priority positions
                if (!empty($highPositions) && !empty($lowPositions)) {
                    $maxHighPosition = max($highPositions);
                    $minLowPosition = min($lowPositions);

                    $this->assertLessThan(
                        $minLowPosition,
                        $maxHighPosition,
                        "All items with 3+ reports (max pos: {$maxHighPosition}) must appear before items with <3 reports (min pos: {$minLowPosition})"
                    );
                }

                // Verify all created items appear in the queue
                foreach ($highPriorityIds as $id) {
                    $this->assertContains($id, $queueIds, "High-priority item {$id} should appear in queue");
                }
                foreach ($lowPriorityIds as $id) {
                    $this->assertContains($id, $queueIds, "Low-priority item {$id} should appear in queue");
                }

                Carbon::setTestNow();
            });
    }

    /**
     * Property: Within the high-priority tier (3+ reports), items are ordered by
     * oldest pending report timestamp (ASC) - content with the earliest pending report comes first.
     */
    public function testHighPriorityTierOrderedByOldestReportTimestamp(): void
    {
        $this->forAll(
            Generators::choose(2, 5), // number of high-priority items
            Generators::choose(1, 10000) // seed
        )
            ->then(function (int $itemCount, int $seed) {
                Carbon::setTestNow(Carbon::now());

                $author = User::factory()->member()->create();

                // Create items with distinct oldest report timestamps
                $items = [];
                for ($i = 0; $i < $itemCount; $i++) {
                    $reportCount = 3 + (($seed * ($i + 1) * 7) % 5); // 3-7 reports
                    // Spread timestamps out so they're distinct - each item's oldest report differs
                    $baseTimestamp = now()->subDays($itemCount - $i)->subHours(($seed * ($i + 1) * 13) % 24);

                    $content = $this->createContentWithReports($author, $reportCount, $baseTimestamp);
                    $items[] = [
                        'id' => $content->id,
                        'oldest_report_at' => $baseTimestamp,
                    ];
                }

                // Get the moderation queue
                $queue = $this->moderationService->getModerationQueue([], 1);
                $queueIds = $queue->pluck('id')->toArray();

                // Extract positions of our high-priority items in the queue
                $itemPositions = [];
                foreach ($items as $item) {
                    $pos = array_search($item['id'], $queueIds);
                    if ($pos !== false) {
                        $itemPositions[] = [
                            'id' => $item['id'],
                            'position' => $pos,
                            'oldest_report_at' => $item['oldest_report_at'],
                        ];
                    }
                }

                // Sort by position in queue
                usort($itemPositions, fn($a, $b) => $a['position'] <=> $b['position']);

                // INVARIANT: Items ordered by position should have non-decreasing oldest_report_at
                for ($i = 1; $i < count($itemPositions); $i++) {
                    $prevTimestamp = $itemPositions[$i - 1]['oldest_report_at'];
                    $currTimestamp = $itemPositions[$i]['oldest_report_at'];

                    $this->assertTrue(
                        $prevTimestamp->lte($currTimestamp),
                        "Within high-priority tier, item at position {$itemPositions[$i-1]['position']} " .
                        "(oldest report: {$prevTimestamp}) should come before item at position " .
                        "{$itemPositions[$i]['position']} (oldest report: {$currTimestamp})"
                    );
                }

                Carbon::setTestNow();
            });
    }

    /**
     * Property: Within the low-priority tier (<3 reports), items are ordered by
     * oldest pending report timestamp (ASC) - content with the earliest pending report comes first.
     */
    public function testLowPriorityTierOrderedByOldestReportTimestamp(): void
    {
        $this->forAll(
            Generators::choose(2, 5), // number of low-priority items
            Generators::choose(1, 10000) // seed
        )
            ->then(function (int $itemCount, int $seed) {
                Carbon::setTestNow(Carbon::now());

                $author = User::factory()->member()->create();

                // Create items with 1-2 reports and distinct oldest report timestamps
                $items = [];
                for ($i = 0; $i < $itemCount; $i++) {
                    $reportCount = 1 + (($seed * ($i + 1) * 11) % 2); // 1-2 reports
                    // Spread timestamps out so they're distinct
                    $baseTimestamp = now()->subDays($itemCount - $i)->subHours(($seed * ($i + 1) * 17) % 24);

                    $content = $this->createContentWithReports($author, $reportCount, $baseTimestamp);
                    $items[] = [
                        'id' => $content->id,
                        'oldest_report_at' => $baseTimestamp,
                    ];
                }

                // Get the moderation queue
                $queue = $this->moderationService->getModerationQueue([], 1);
                $queueIds = $queue->pluck('id')->toArray();

                // Extract positions of our low-priority items in the queue
                $itemPositions = [];
                foreach ($items as $item) {
                    $pos = array_search($item['id'], $queueIds);
                    if ($pos !== false) {
                        $itemPositions[] = [
                            'id' => $item['id'],
                            'position' => $pos,
                            'oldest_report_at' => $item['oldest_report_at'],
                        ];
                    }
                }

                // Sort by position in queue
                usort($itemPositions, fn($a, $b) => $a['position'] <=> $b['position']);

                // INVARIANT: Items ordered by position should have non-decreasing oldest_report_at
                for ($i = 1; $i < count($itemPositions); $i++) {
                    $prevTimestamp = $itemPositions[$i - 1]['oldest_report_at'];
                    $currTimestamp = $itemPositions[$i]['oldest_report_at'];

                    $this->assertTrue(
                        $prevTimestamp->lte($currTimestamp),
                        "Within low-priority tier, item at position {$itemPositions[$i-1]['position']} " .
                        "(oldest report: {$prevTimestamp}) should come before item at position " .
                        "{$itemPositions[$i]['position']} (oldest report: {$currTimestamp})"
                    );
                }

                Carbon::setTestNow();
            });
    }

    /**
     * Property: Mixed queue maintains correct tier ordering with proper within-tier ordering.
     *
     * Generate a mixed set of content items with varied report counts and timestamps.
     * Verify the complete ordering invariant: high-priority first, then low-priority,
     * each tier sorted by oldest pending report timestamp (ASC).
     */
    public function testMixedQueueMaintainsFullOrderingInvariant(): void
    {
        $this->forAll(
            Generators::choose(1, 3), // number of high-priority items
            Generators::choose(1, 3), // number of low-priority items
            Generators::choose(1, 10000) // seed
        )
            ->then(function (int $highCount, int $lowCount, int $seed) {
                Carbon::setTestNow(Carbon::now());

                $author = User::factory()->member()->create();

                $highItems = [];
                $lowItems = [];

                // Create high-priority content (3+ pending reports) with varied timestamps
                for ($i = 0; $i < $highCount; $i++) {
                    $reportCount = 3 + (($seed * ($i + 1) * 7) % 5); // 3-7 reports
                    $baseTimestamp = now()->subDays(20 + $i * 3)->subHours(($seed * ($i + 1) * 13) % 12);

                    $content = $this->createContentWithReports($author, $reportCount, $baseTimestamp);
                    $highItems[] = [
                        'id' => $content->id,
                        'oldest_report_at' => $baseTimestamp,
                        'report_count' => $reportCount,
                    ];
                }

                // Create low-priority content (1-2 pending reports) with varied timestamps
                // Note: some low-priority items have OLDER timestamps than high-priority ones
                // They should STILL appear after all high-priority items
                for ($i = 0; $i < $lowCount; $i++) {
                    $reportCount = 1 + (($seed * ($i + 1) * 11) % 2); // 1-2 reports
                    // Deliberately make some low-priority items have older timestamps
                    $baseTimestamp = now()->subDays(30 + $i * 5)->subHours(($seed * ($i + 1) * 17) % 12);

                    $content = $this->createContentWithReports($author, $reportCount, $baseTimestamp);
                    $lowItems[] = [
                        'id' => $content->id,
                        'oldest_report_at' => $baseTimestamp,
                        'report_count' => $reportCount,
                    ];
                }

                // Get the moderation queue
                $queue = $this->moderationService->getModerationQueue([], 1);
                $queueIds = $queue->pluck('id')->toArray();

                $highPriorityIds = array_column($highItems, 'id');
                $lowPriorityIds = array_column($lowItems, 'id');

                // INVARIANT 1: All high-priority items appear before all low-priority items
                $highPositions = [];
                $lowPositions = [];
                foreach ($queueIds as $pos => $id) {
                    if (in_array($id, $highPriorityIds)) {
                        $highPositions[] = $pos;
                    }
                    if (in_array($id, $lowPriorityIds)) {
                        $lowPositions[] = $pos;
                    }
                }

                if (!empty($highPositions) && !empty($lowPositions)) {
                    $this->assertLessThan(
                        min($lowPositions),
                        max($highPositions),
                        "All high-priority items must appear before low-priority items, " .
                        "even when low-priority items have older report timestamps"
                    );
                }

                // INVARIANT 2: Within high-priority tier, ordered by oldest report ASC
                $highInQueue = [];
                foreach ($highItems as $item) {
                    $pos = array_search($item['id'], $queueIds);
                    if ($pos !== false) {
                        $highInQueue[] = [
                            'position' => $pos,
                            'oldest_report_at' => $item['oldest_report_at'],
                        ];
                    }
                }
                usort($highInQueue, fn($a, $b) => $a['position'] <=> $b['position']);

                for ($i = 1; $i < count($highInQueue); $i++) {
                    $this->assertTrue(
                        $highInQueue[$i - 1]['oldest_report_at']->lte($highInQueue[$i]['oldest_report_at']),
                        "High-priority tier: items should be ordered by oldest report timestamp ASC"
                    );
                }

                // INVARIANT 3: Within low-priority tier, ordered by oldest report ASC
                $lowInQueue = [];
                foreach ($lowItems as $item) {
                    $pos = array_search($item['id'], $queueIds);
                    if ($pos !== false) {
                        $lowInQueue[] = [
                            'position' => $pos,
                            'oldest_report_at' => $item['oldest_report_at'],
                        ];
                    }
                }
                usort($lowInQueue, fn($a, $b) => $a['position'] <=> $b['position']);

                for ($i = 1; $i < count($lowInQueue); $i++) {
                    $this->assertTrue(
                        $lowInQueue[$i - 1]['oldest_report_at']->lte($lowInQueue[$i]['oldest_report_at']),
                        "Low-priority tier: items should be ordered by oldest report timestamp ASC"
                    );
                }

                Carbon::setTestNow();
            });
    }
}
