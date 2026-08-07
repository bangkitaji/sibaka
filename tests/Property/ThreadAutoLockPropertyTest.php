<?php

namespace Tests\Property;

use App\Jobs\AutoLockThreads;
use App\Models\Comment;
use App\Models\Content;
use App\Models\User;
use Carbon\Carbon;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property 18: Thread Auto-Lock
 *
 * For any set of threads (content) with varied last activity timestamps,
 * AutoLockThreads SHALL lock exactly those threads where last activity is
 * more than 90 days ago, and SHALL NOT lock threads with activity within
 * the last 90 days. Already-locked threads remain locked (not double-processed).
 *
 * Last activity is defined as the most recent comment's created_at timestamp,
 * or the content's created_at if there are no comments.
 *
 * **Validates: Requirements 7.6**
 */
class ThreadAutoLockPropertyTest extends TestCase
{
    use TestTrait;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Property: Threads with last activity > 90 days ago get locked.
     *
     * Generate random threads with no comments where created_at is older than 90 days.
     * After running AutoLockThreads, all should be locked.
     */
    public function testThreadsWithLastActivityOlderThan90DaysGetLocked(): void
    {
        $this->forAll(
            Generators::choose(1, 5) // number of threads to create
        )
            ->then(function (int $threadCount) {
                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create();

                $threadIds = [];
                for ($i = 0; $i < $threadCount; $i++) {
                    // Content created 91-365 days ago, no comments (last activity = created_at)
                    $daysOld = rand(91, 365);
                    $content = Content::factory()->published()->create([
                        'author_id' => $user->id,
                        'is_locked' => false,
                        'locked_at' => null,
                        'created_at' => now()->subDays($daysOld),
                        'updated_at' => now()->subDays($daysOld),
                    ]);
                    $threadIds[] = $content->id;
                }

                // Run the auto-lock job
                (new AutoLockThreads())->handle();

                // Verify all old threads are now locked
                foreach ($threadIds as $id) {
                    $this->assertDatabaseHas('contents', [
                        'id' => $id,
                        'is_locked' => true,
                    ]);
                }

                Carbon::setTestNow();
            });
    }

    /**
     * Property: Threads with last activity <= 90 days remain unlocked.
     *
     * Generate random threads with no comments where created_at is within 90 days.
     * After running AutoLockThreads, none should be locked.
     */
    public function testThreadsWithLastActivityWithin90DaysRemainUnlocked(): void
    {
        $this->forAll(
            Generators::choose(1, 5) // number of threads to create
        )
            ->then(function (int $threadCount) {
                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create();

                $threadIds = [];
                for ($i = 0; $i < $threadCount; $i++) {
                    // Content created 0-89 days ago, no comments (last activity = created_at)
                    $daysOld = rand(0, 89);
                    $content = Content::factory()->published()->create([
                        'author_id' => $user->id,
                        'is_locked' => false,
                        'locked_at' => null,
                        'created_at' => now()->subDays($daysOld),
                        'updated_at' => now()->subDays($daysOld),
                    ]);
                    $threadIds[] = $content->id;
                }

                // Run the auto-lock job
                (new AutoLockThreads())->handle();

                // Verify none are locked
                foreach ($threadIds as $id) {
                    $this->assertDatabaseHas('contents', [
                        'id' => $id,
                        'is_locked' => false,
                    ]);
                }

                Carbon::setTestNow();
            });
    }

    /**
     * Property: Already-locked threads remain locked (not double-processed).
     *
     * Generate random threads that are already locked. After running AutoLockThreads,
     * they should still be locked with their original locked_at timestamp preserved.
     */
    public function testAlreadyLockedThreadsRemainLocked(): void
    {
        $this->forAll(
            Generators::choose(1, 5) // number of already-locked threads
        )
            ->then(function (int $threadCount) {
                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create();

                $threads = [];
                for ($i = 0; $i < $threadCount; $i++) {
                    $daysOld = rand(91, 365);
                    $lockedAt = now()->subDays(rand(1, 30)); // locked recently
                    $content = Content::factory()->published()->create([
                        'author_id' => $user->id,
                        'is_locked' => true,
                        'locked_at' => $lockedAt,
                        'created_at' => now()->subDays($daysOld),
                        'updated_at' => now()->subDays($daysOld),
                    ]);
                    $threads[] = ['id' => $content->id, 'locked_at' => $lockedAt->toDateTimeString()];
                }

                // Run the auto-lock job
                (new AutoLockThreads())->handle();

                // Verify all remain locked with original locked_at timestamp
                foreach ($threads as $thread) {
                    $content = Content::find($thread['id']);
                    $this->assertTrue($content->is_locked);
                    $this->assertEquals(
                        $thread['locked_at'],
                        $content->locked_at->toDateTimeString(),
                        'Already-locked thread should retain its original locked_at timestamp'
                    );
                }

                Carbon::setTestNow();
            });
    }

    /**
     * Property: Thread with recent comment (within 90 days) remains unlocked even if content is old.
     *
     * Generate threads created > 90 days ago but with at least one comment within 90 days.
     * These should NOT be locked because last activity = most recent comment.
     */
    public function testThreadWithRecentCommentRemainsUnlocked(): void
    {
        $this->forAll(
            Generators::choose(1, 4) // number of threads
        )
            ->then(function (int $threadCount) {
                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create();

                $threadIds = [];
                for ($i = 0; $i < $threadCount; $i++) {
                    // Content created long ago (> 90 days)
                    $contentDaysOld = rand(91, 365);
                    $content = Content::factory()->published()->create([
                        'author_id' => $user->id,
                        'is_locked' => false,
                        'locked_at' => null,
                        'created_at' => now()->subDays($contentDaysOld),
                        'updated_at' => now()->subDays($contentDaysOld),
                    ]);

                    // Add a recent comment (within 90 days)
                    $commentDaysOld = rand(0, 89);
                    Comment::factory()->create([
                        'content_id' => $content->id,
                        'author_id' => $user->id,
                        'created_at' => now()->subDays($commentDaysOld),
                        'updated_at' => now()->subDays($commentDaysOld),
                    ]);

                    $threadIds[] = $content->id;
                }

                // Run the auto-lock job
                (new AutoLockThreads())->handle();

                // Verify none are locked because they have recent comments
                foreach ($threadIds as $id) {
                    $this->assertDatabaseHas('contents', [
                        'id' => $id,
                        'is_locked' => false,
                    ]);
                }

                Carbon::setTestNow();
            });
    }

    /**
     * Property: Thread with all comments older than 90 days gets locked.
     *
     * Generate threads with comments that are all older than 90 days.
     * These should be locked because last activity (most recent comment) is > 90 days.
     */
    public function testThreadWithOnlyOldCommentsGetsLocked(): void
    {
        $this->forAll(
            Generators::choose(1, 4) // number of threads
        )
            ->then(function (int $threadCount) {
                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create();

                $threadIds = [];
                for ($i = 0; $i < $threadCount; $i++) {
                    // Content created long ago
                    $contentDaysOld = rand(120, 365);
                    $content = Content::factory()->published()->create([
                        'author_id' => $user->id,
                        'is_locked' => false,
                        'locked_at' => null,
                        'created_at' => now()->subDays($contentDaysOld),
                        'updated_at' => now()->subDays($contentDaysOld),
                    ]);

                    // Add 1-3 comments, all older than 90 days
                    $commentCount = rand(1, 3);
                    for ($j = 0; $j < $commentCount; $j++) {
                        $commentDaysOld = rand(91, $contentDaysOld);
                        Comment::factory()->create([
                            'content_id' => $content->id,
                            'author_id' => $user->id,
                            'created_at' => now()->subDays($commentDaysOld),
                            'updated_at' => now()->subDays($commentDaysOld),
                        ]);
                    }

                    $threadIds[] = $content->id;
                }

                // Run the auto-lock job
                (new AutoLockThreads())->handle();

                // Verify all are locked
                foreach ($threadIds as $id) {
                    $this->assertDatabaseHas('contents', [
                        'id' => $id,
                        'is_locked' => true,
                    ]);
                }

                Carbon::setTestNow();
            });
    }

    /**
     * Property: Mixed set - only threads with last activity > 90 days get locked.
     *
     * Generate a mixed set of threads: some old (no recent activity), some with recent activity.
     * Verify that exactly the old ones get locked and the recent ones stay unlocked.
     */
    public function testMixedThreadsOnlyOldOnesGetLocked(): void
    {
        $this->forAll(
            Generators::choose(1, 3), // number of old threads (should lock)
            Generators::choose(1, 3)  // number of active threads (should stay unlocked)
        )
            ->then(function (int $oldCount, int $activeCount) {
                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create();

                $shouldLockIds = [];
                $shouldNotLockIds = [];

                // Create old threads (last activity > 90 days, no comments)
                for ($i = 0; $i < $oldCount; $i++) {
                    $daysOld = rand(91, 365);
                    $content = Content::factory()->published()->create([
                        'author_id' => $user->id,
                        'is_locked' => false,
                        'locked_at' => null,
                        'created_at' => now()->subDays($daysOld),
                        'updated_at' => now()->subDays($daysOld),
                    ]);
                    $shouldLockIds[] = $content->id;
                }

                // Create active threads (last activity <= 90 days)
                for ($i = 0; $i < $activeCount; $i++) {
                    $daysOld = rand(0, 89);
                    $content = Content::factory()->published()->create([
                        'author_id' => $user->id,
                        'is_locked' => false,
                        'locked_at' => null,
                        'created_at' => now()->subDays($daysOld),
                        'updated_at' => now()->subDays($daysOld),
                    ]);
                    $shouldNotLockIds[] = $content->id;
                }

                // Run the auto-lock job
                (new AutoLockThreads())->handle();

                // Verify old threads got locked
                foreach ($shouldLockIds as $id) {
                    $this->assertDatabaseHas('contents', [
                        'id' => $id,
                        'is_locked' => true,
                    ]);
                }

                // Verify active threads stayed unlocked
                foreach ($shouldNotLockIds as $id) {
                    $this->assertDatabaseHas('contents', [
                        'id' => $id,
                        'is_locked' => false,
                    ]);
                }

                Carbon::setTestNow();
            });
    }
}
