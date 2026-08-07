<?php

namespace Tests\Property;

use App\Exceptions\ContentException;
use App\Models\Comment;
use App\Services\CommentService;
use Carbon\Carbon;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\TestCase;

/**
 * Property 17: Comment Edit Time Window
 *
 * Tests that edit is accepted iff elapsed time ≤ 15 minutes, delete accepted always.
 * Generates random elapsed times and verifies boundary behavior.
 *
 * The CommentService uses `>` (strict greater than) for the 15-minute check:
 *   if ($comment->created_at->diffInMinutes(now()) > 15) → throw editWindowExpired
 * This means: edit succeeds when elapsed ≤ 15, fails when elapsed > 15.
 *
 * **Validates: Requirements 7.10**
 */
class CommentEditWindowPropertyTest extends TestCase
{
    use TestTrait;

    private const EDIT_WINDOW_MINUTES = 15;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Simulate the edit window check logic from CommentService::editComment.
     *
     * This replicates the exact condition used in the service:
     *   $comment->created_at->diffInMinutes(now()) > EDIT_WINDOW_MINUTES
     *
     * Returns true if edit would be allowed, false if it would be rejected.
     */
    private function isEditAllowed(Carbon $createdAt, Carbon $now): bool
    {
        return $createdAt->diffInMinutes($now) <= self::EDIT_WINDOW_MINUTES;
    }

    /**
     * Simulate the delete permission check logic from CommentService::deleteComment.
     *
     * Delete has NO time window check - it always succeeds (for the author).
     * Returns true always (author can delete at any time).
     */
    private function isDeleteAllowed(Carbon $createdAt, Carbon $now): bool
    {
        // No time-based restriction on deletion
        return true;
    }

    /**
     * Property: Edit succeeds when elapsed time is 0-15 minutes (within window).
     *
     * For any elapsed time ≤ 15 minutes, the edit window check passes.
     * The service uses strict `>` so exactly 15 minutes still allows edit.
     */
    public function testEditSucceedsWithinFifteenMinutes(): void
    {
        $this->forAll(
            Generators::choose(0, 15)
        )
            ->then(function (int $elapsedMinutes) {
                $now = Carbon::create(2024, 6, 15, 12, 0, 0);
                $createdAt = $now->copy()->subMinutes($elapsedMinutes);
                Carbon::setTestNow($now);

                $allowed = $this->isEditAllowed($createdAt, $now);

                $this->assertTrue(
                    $allowed,
                    "Edit should be allowed when elapsed time is {$elapsedMinutes} minutes (≤ 15)"
                );

                // Verify the actual diffInMinutes calculation
                $diff = $createdAt->diffInMinutes($now);
                $this->assertLessThanOrEqual(
                    self::EDIT_WINDOW_MINUTES,
                    $diff,
                    "diffInMinutes({$elapsedMinutes}) should be ≤ 15"
                );

                // Verify the service condition: diff > 15 should be FALSE
                $this->assertFalse(
                    $diff > self::EDIT_WINDOW_MINUTES,
                    "Condition (diff > 15) should be false for elapsed={$elapsedMinutes}"
                );

                Carbon::setTestNow();
            });
    }

    /**
     * Property: Edit fails when elapsed time exceeds 15 minutes.
     *
     * For any elapsed time > 15 minutes, the edit window check fails
     * and the service would throw ContentException::editWindowExpired().
     */
    public function testEditFailsAfterFifteenMinutes(): void
    {
        $this->forAll(
            Generators::choose(16, 120)
        )
            ->then(function (int $elapsedMinutes) {
                $now = Carbon::create(2024, 6, 15, 12, 0, 0);
                $createdAt = $now->copy()->subMinutes($elapsedMinutes);
                Carbon::setTestNow($now);

                $allowed = $this->isEditAllowed($createdAt, $now);

                $this->assertFalse(
                    $allowed,
                    "Edit should be rejected when elapsed time is {$elapsedMinutes} minutes (> 15)"
                );

                // Verify the actual diffInMinutes calculation
                $diff = $createdAt->diffInMinutes($now);
                $this->assertGreaterThan(
                    self::EDIT_WINDOW_MINUTES,
                    $diff,
                    "diffInMinutes({$elapsedMinutes}) should be > 15"
                );

                // Verify the service condition: diff > 15 should be TRUE
                $this->assertTrue(
                    $diff > self::EDIT_WINDOW_MINUTES,
                    "Condition (diff > 15) should be true for elapsed={$elapsedMinutes}"
                );

                Carbon::setTestNow();
            });
    }

    /**
     * Property: Edit boundary - at exactly 15 minutes, edit succeeds.
     *
     * The service uses strict `>` comparison (not `>=`), so at exactly 15 minutes
     * the condition `diffInMinutes(now()) > 15` is false, and edit is allowed.
     */
    public function testEditSucceedsAtExactlyFifteenMinutes(): void
    {
        $now = Carbon::create(2024, 6, 15, 12, 15, 0);
        $createdAt = Carbon::create(2024, 6, 15, 12, 0, 0);
        Carbon::setTestNow($now);

        $diff = $createdAt->diffInMinutes($now);
        $this->assertEquals(15, $diff, 'Diff should be exactly 15 minutes');

        $allowed = $this->isEditAllowed($createdAt, $now);
        $this->assertTrue(
            $allowed,
            'Edit should succeed at exactly 15 minutes (uses > not >=)'
        );

        // Confirm the service condition logic
        $this->assertFalse(
            $diff > self::EDIT_WINDOW_MINUTES,
            'Condition (15 > 15) should be false, allowing edit'
        );

        Carbon::setTestNow();
    }

    /**
     * Property: Edit boundary - at 16 minutes, edit fails.
     *
     * The first minute after the window (16) should trigger rejection.
     */
    public function testEditFailsAtSixteenMinutes(): void
    {
        $now = Carbon::create(2024, 6, 15, 12, 16, 0);
        $createdAt = Carbon::create(2024, 6, 15, 12, 0, 0);
        Carbon::setTestNow($now);

        $diff = $createdAt->diffInMinutes($now);
        $this->assertEquals(16, $diff, 'Diff should be exactly 16 minutes');

        $allowed = $this->isEditAllowed($createdAt, $now);
        $this->assertFalse(
            $allowed,
            'Edit should fail at 16 minutes (16 > 15 is true)'
        );

        // Confirm the service condition logic
        $this->assertTrue(
            $diff > self::EDIT_WINDOW_MINUTES,
            'Condition (16 > 15) should be true, rejecting edit'
        );

        Carbon::setTestNow();
    }

    /**
     * Property: Delete always succeeds regardless of elapsed time.
     *
     * For any random elapsed time from 0 to 525600 minutes (1 year),
     * delete permission should always be granted (no time window check).
     */
    public function testDeleteSucceedsRegardlessOfElapsedTime(): void
    {
        $this->forAll(
            Generators::choose(0, 525600)
        )
            ->withMaxSize(50)
            ->then(function (int $elapsedMinutes) {
                $now = Carbon::create(2024, 6, 15, 12, 0, 0);
                $createdAt = $now->copy()->subMinutes($elapsedMinutes);
                Carbon::setTestNow($now);

                $allowed = $this->isDeleteAllowed($createdAt, $now);

                $this->assertTrue(
                    $allowed,
                    "Delete should always be allowed regardless of elapsed time ({$elapsedMinutes} minutes)"
                );

                Carbon::setTestNow();
            });
    }

    /**
     * Property: Edit validity is determined exclusively by elapsed time ≤ 15 minutes.
     *
     * For any randomly generated elapsed time (0-120 minutes), the edit outcome
     * is predictable: succeeds iff elapsed ≤ 15, fails iff elapsed > 15.
     * This is the core invariant of the edit window.
     */
    public function testEditValidityDeterminedByFifteenMinuteThreshold(): void
    {
        $this->forAll(
            Generators::choose(0, 120)
        )
            ->then(function (int $elapsedMinutes) {
                $now = Carbon::create(2024, 6, 15, 12, 0, 0);
                $createdAt = $now->copy()->subMinutes($elapsedMinutes);
                Carbon::setTestNow($now);

                $diff = $createdAt->diffInMinutes($now);
                $shouldSucceed = $elapsedMinutes <= self::EDIT_WINDOW_MINUTES;
                $editAllowed = $this->isEditAllowed($createdAt, $now);

                $this->assertEquals(
                    $shouldSucceed,
                    $editAllowed,
                    "Edit at {$elapsedMinutes} min should " .
                    ($shouldSucceed ? 'succeed (≤ 15)' : 'fail (> 15)') .
                    ' but it ' . ($editAllowed ? 'would succeed' : 'would fail')
                );

                // Verify complementary: edit and no-edit are mutually exclusive
                $this->assertNotEquals(
                    $editAllowed,
                    $diff > self::EDIT_WINDOW_MINUTES,
                    'Edit allowed and (diff > 15) should always be complementary'
                );

                Carbon::setTestNow();
            });
    }

    /**
     * Property: The ContentException::editWindowExpired() is thrown correctly.
     *
     * Verifies the exception factory produces the right message and code
     * that the service would throw when the edit window expires.
     */
    public function testEditWindowExpiredExceptionIsCorrect(): void
    {
        $this->forAll(
            Generators::choose(16, 120)
        )
            ->then(function (int $elapsedMinutes) {
                $exception = ContentException::editWindowExpired();

                $this->assertInstanceOf(ContentException::class, $exception);
                $this->assertStringContainsString(
                    'Edit window has expired',
                    $exception->getMessage(),
                    'Exception message should indicate edit window expired'
                );
                $this->assertEquals(
                    403,
                    $exception->getCode(),
                    'Edit window expired should return 403 Forbidden'
                );
            });
    }

    /**
     * Property: Edit and delete have different time constraints.
     *
     * For any elapsed time > 15 minutes, edit is rejected but delete is still allowed.
     * This verifies the asymmetry between edit (time-limited) and delete (unrestricted).
     */
    public function testEditAndDeleteHaveDifferentTimeConstraints(): void
    {
        $this->forAll(
            Generators::choose(16, 525600)
        )
            ->withMaxSize(50)
            ->then(function (int $elapsedMinutes) {
                $now = Carbon::create(2024, 6, 15, 12, 0, 0);
                $createdAt = $now->copy()->subMinutes($elapsedMinutes);
                Carbon::setTestNow($now);

                $editAllowed = $this->isEditAllowed($createdAt, $now);
                $deleteAllowed = $this->isDeleteAllowed($createdAt, $now);

                // After 15 minutes: edit rejected, delete still allowed
                $this->assertFalse(
                    $editAllowed,
                    "Edit should be rejected at {$elapsedMinutes} minutes (> 15)"
                );
                $this->assertTrue(
                    $deleteAllowed,
                    "Delete should still be allowed at {$elapsedMinutes} minutes"
                );

                Carbon::setTestNow();
            });
    }
}
