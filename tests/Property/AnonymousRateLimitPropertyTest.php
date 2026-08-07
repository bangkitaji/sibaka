<?php

namespace Tests\Property;

use App\Models\AnonymousMetadata;
use App\Models\User;
use App\Services\AnonymityService;
use Carbon\Carbon;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property 9: Anonymous Posting Rate Limit
 *
 * For any member who has published N anonymous posts within the last 24 hours,
 * a new anonymous post attempt SHALL be accepted if N < 5 and rejected if N >= 5.
 *
 * **Validates: Requirements 5.9**
 */
class AnonymousRateLimitPropertyTest extends TestCase
{
    use TestTrait;
    use RefreshDatabase;

    private AnonymityService $anonymityService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->anonymityService = new AnonymityService();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Property: canPublishAnonymously returns true when N < 5 posts in last 24h.
     *
     * Generate random N from 0-4, create N metadata records within last 24h,
     * verify canPublishAnonymously returns true.
     */
    public function testCanPublishWhenFewerThanFivePostsInTwentyFourHours(): void
    {
        $this->forAll(
            Generators::choose(0, 4)
        )
            ->then(function (int $postCount) {
                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create();

                // Create N anonymous posts within the last 24 hours
                for ($i = 0; $i < $postCount; $i++) {
                    AnonymousMetadata::factory()->create([
                        'author_id' => $user->id,
                        'created_at' => now()->subHours(rand(0, 23)),
                    ]);
                }

                $result = $this->anonymityService->canPublishAnonymously($user->id);

                $this->assertTrue(
                    $result,
                    "canPublishAnonymously should return true with {$postCount} posts in 24h (< 5)"
                );

                Carbon::setTestNow();
            });
    }

    /**
     * Property: canPublishAnonymously returns false when N >= 5 posts in last 24h.
     *
     * Generate random N from 5-10, create N metadata records within last 24h,
     * verify canPublishAnonymously returns false.
     */
    public function testCannotPublishWhenFiveOrMorePostsInTwentyFourHours(): void
    {
        $this->forAll(
            Generators::choose(5, 10)
        )
            ->then(function (int $postCount) {
                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create();

                // Create N anonymous posts within the last 24 hours
                for ($i = 0; $i < $postCount; $i++) {
                    AnonymousMetadata::factory()->create([
                        'author_id' => $user->id,
                        'created_at' => now()->subHours(rand(0, 23)),
                    ]);
                }

                $result = $this->anonymityService->canPublishAnonymously($user->id);

                $this->assertFalse(
                    $result,
                    "canPublishAnonymously should return false with {$postCount} posts in 24h (>= 5)"
                );

                Carbon::setTestNow();
            });
    }

    /**
     * Property: At the boundary (exactly 5 posts), canPublishAnonymously returns false.
     */
    public function testCannotPublishAtExactlyFivePostsBoundary(): void
    {
        $this->forAll(
            Generators::choose(1, 23) // random hour offset for timestamp variation
        )
            ->then(function (int $hourOffset) {
                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create();

                // Create exactly 5 posts within the last 24 hours
                for ($i = 0; $i < 5; $i++) {
                    AnonymousMetadata::factory()->create([
                        'author_id' => $user->id,
                        'created_at' => now()->subHours($hourOffset),
                    ]);
                }

                $result = $this->anonymityService->canPublishAnonymously($user->id);

                $this->assertFalse(
                    $result,
                    'canPublishAnonymously should return false with exactly 5 posts in 24h'
                );

                Carbon::setTestNow();
            });
    }

    /**
     * Property: Posts older than 24 hours do NOT count toward the rate limit.
     *
     * Generate random N from 5-10 posts all older than 24h, verify canPublishAnonymously
     * returns true since none are within the window.
     */
    public function testPostsOlderThanTwentyFourHoursDoNotCountTowardLimit(): void
    {
        $this->forAll(
            Generators::choose(5, 10)
        )
            ->then(function (int $postCount) {
                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create();

                // Create N posts that are all OLDER than 24 hours
                for ($i = 0; $i < $postCount; $i++) {
                    AnonymousMetadata::factory()->create([
                        'author_id' => $user->id,
                        'created_at' => now()->subHours(rand(25, 72)),
                    ]);
                }

                $result = $this->anonymityService->canPublishAnonymously($user->id);

                $this->assertTrue(
                    $result,
                    "canPublishAnonymously should return true when all {$postCount} posts are older than 24h"
                );

                Carbon::setTestNow();
            });
    }

    /**
     * Property: Only posts within 24h window count. Mixed old + recent posts
     * should be evaluated correctly based on recent count only.
     *
     * Generate random recent count (0-4) and old count (5-10), verify
     * canPublishAnonymously returns true since recent < 5.
     */
    public function testMixedOldAndRecentPostsOnlyCountRecent(): void
    {
        $this->forAll(
            Generators::choose(0, 4),  // recent posts (within 24h)
            Generators::choose(5, 10)  // old posts (older than 24h)
        )
            ->then(function (int $recentCount, int $oldCount) {
                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create();

                // Create recent posts (within 24h)
                for ($i = 0; $i < $recentCount; $i++) {
                    AnonymousMetadata::factory()->create([
                        'author_id' => $user->id,
                        'created_at' => now()->subHours(rand(0, 23)),
                    ]);
                }

                // Create old posts (older than 24h)
                for ($i = 0; $i < $oldCount; $i++) {
                    AnonymousMetadata::factory()->create([
                        'author_id' => $user->id,
                        'created_at' => now()->subHours(rand(25, 72)),
                    ]);
                }

                $result = $this->anonymityService->canPublishAnonymously($user->id);

                $this->assertTrue(
                    $result,
                    "canPublishAnonymously should return true with {$recentCount} recent posts (< 5) even with {$oldCount} old posts"
                );

                Carbon::setTestNow();
            });
    }

    /**
     * Property: The general invariant - canPublishAnonymously returns true iff
     * recent post count < 5.
     *
     * Generate random N from 0-10, create N posts within 24h, verify the result
     * matches the property: true iff N < 5.
     */
    public function testRateLimitInvariant(): void
    {
        $this->forAll(
            Generators::choose(0, 10)
        )
            ->then(function (int $postCount) {
                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create();

                // Create N anonymous posts within the last 24 hours
                for ($i = 0; $i < $postCount; $i++) {
                    AnonymousMetadata::factory()->create([
                        'author_id' => $user->id,
                        'created_at' => now()->subHours(rand(0, 23)),
                    ]);
                }

                $result = $this->anonymityService->canPublishAnonymously($user->id);
                $expected = $postCount < 5;

                $this->assertEquals(
                    $expected,
                    $result,
                    "canPublishAnonymously should return " . ($expected ? 'true' : 'false')
                    . " with {$postCount} posts in 24h (threshold: 5)"
                );

                Carbon::setTestNow();
            });
    }
}
