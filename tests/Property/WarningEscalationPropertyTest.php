<?php

namespace Tests\Property;

use App\Models\User;
use App\Models\Warning;
use App\Services\AuditLogService;
use App\Services\ModerationService;
use Carbon\Carbon;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Property 30: Warning Escalation
 *
 * For any member with N active warnings within the past 90 days, a new warning
 * SHALL trigger automatic 7-day suspension if N+1 >= 3. Members with fewer than
 * 3 warnings (including the new one) within 90 days SHALL not be auto-suspended.
 *
 * Properties tested:
 * 1. User with 2 existing warnings in 90 days + new warning (3rd) → auto-suspended for 7 days
 * 2. User with 0-1 existing warnings in 90 days + new warning → NOT suspended
 * 3. Warnings older than 90 days don't count toward escalation
 *
 * **Validates: Requirements 12.7**
 */
class WarningEscalationPropertyTest extends TestCase
{
    use TestTrait;
    use RefreshDatabase;

    private ModerationService $moderationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->moderationService = app(ModerationService::class);
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Helper: Create warnings for a user at specified days-ago offsets.
     *
     * @param User $user
     * @param User $moderator
     * @param array<int> $daysAgoOffsets - each entry is the number of days ago the warning was created
     */
    private function createWarningsAtOffsets(User $user, User $moderator, array $daysAgoOffsets): void
    {
        foreach ($daysAgoOffsets as $daysAgo) {
            $timestamp = now()->subDays($daysAgo);
            Warning::factory()->create([
                'user_id' => $user->id,
                'issued_by' => $moderator->id,
                'message' => "Warning issued {$daysAgo} days ago",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }

    /**
     * Property: User with 2 existing warnings within 90 days receives auto-suspension
     * on the 3rd warning (N+1 = 3 >= 3).
     *
     * Generate random timestamps within 90 days for 2 existing warnings, then issue a new one.
     * The user MUST be auto-suspended for exactly 7 days.
     */
    public function testThirdWarningWithin90DaysTriggersSuspension(): void
    {
        $this->forAll(
            Generators::choose(0, 89), // days ago for first existing warning
            Generators::choose(0, 89)  // days ago for second existing warning
        )
            ->then(function (int $daysAgo1, int $daysAgo2) {
                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create([
                    'is_suspended' => false,
                    'suspended_until' => null,
                ]);
                $moderator = User::factory()->moderator()->create();

                // Create 2 existing warnings within 90 days
                $this->createWarningsAtOffsets($user, $moderator, [$daysAgo1, $daysAgo2]);

                // Issue the 3rd warning (this should trigger escalation)
                $this->moderationService->issueWarning(
                    $user->id,
                    'Third warning - escalation test',
                    $moderator->id,
                    '127.0.0.1'
                );

                // Refresh user from database
                $user->refresh();

                // INVARIANT: User must be auto-suspended
                $this->assertTrue(
                    $user->is_suspended,
                    "User with 3 warnings within 90 days (at offsets {$daysAgo1}d, {$daysAgo2}d, 0d) " .
                    "must be auto-suspended"
                );

                // INVARIANT: Suspension must be for 7 days
                $this->assertNotNull($user->suspended_until);
                $expectedSuspendedUntil = now()->addDays(7);
                $this->assertTrue(
                    $user->suspended_until->diffInSeconds($expectedSuspendedUntil) < 5,
                    "Suspension must be for 7 days. Expected ~{$expectedSuspendedUntil}, got {$user->suspended_until}"
                );

                Carbon::setTestNow();
            });
    }

    /**
     * Property: User with 0 or 1 existing warnings within 90 days does NOT get suspended
     * when a new warning is issued (N+1 < 3).
     *
     * Generate random counts (0 or 1) and timestamps for existing warnings.
     * User MUST NOT be suspended after receiving the new warning.
     */
    public function testFewerThanThreeWarningsDoesNotTriggerSuspension(): void
    {
        $this->forAll(
            Generators::choose(0, 1),  // existing warning count (0 or 1)
            Generators::choose(0, 89)  // days ago for existing warning (if any)
        )
            ->then(function (int $existingCount, int $daysAgo) {
                // Clean up from previous iterations
                Warning::query()->delete();

                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create([
                    'is_suspended' => false,
                    'suspended_until' => null,
                ]);
                $moderator = User::factory()->moderator()->create();

                // Create 0 or 1 existing warnings within 90 days
                if ($existingCount === 1) {
                    $this->createWarningsAtOffsets($user, $moderator, [$daysAgo]);
                }

                // Issue a new warning (resulting in 1 or 2 total, which is < 3)
                $this->moderationService->issueWarning(
                    $user->id,
                    'Warning - no escalation expected',
                    $moderator->id,
                    '127.0.0.1'
                );

                // Refresh user from database
                $user->refresh();

                // INVARIANT: User must NOT be auto-suspended
                $this->assertFalse(
                    $user->is_suspended,
                    "User with {$existingCount} existing warnings + 1 new = " . ($existingCount + 1) .
                    " total (< 3) must NOT be suspended"
                );
                $this->assertNull(
                    $user->suspended_until,
                    "suspended_until must remain null when total warnings < 3"
                );

                Carbon::setTestNow();
            });
    }

    /**
     * Property: Warnings older than 90 days do NOT count toward escalation threshold.
     *
     * Generate users with multiple warnings that are all older than 90 days, then issue a new one.
     * Even if total historical warnings >= 3, only recent ones (within 90 days) should count.
     * The new warning alone (1 within 90 days) should NOT trigger suspension.
     */
    public function testWarningsOlderThan90DaysDoNotCount(): void
    {
        $this->forAll(
            Generators::choose(2, 5),   // number of old warnings (>90 days ago)
            Generators::choose(91, 365) // days ago for old warnings (all >90)
        )
            ->then(function (int $oldWarningCount, int $baseDaysAgo) {
                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create([
                    'is_suspended' => false,
                    'suspended_until' => null,
                ]);
                $moderator = User::factory()->moderator()->create();

                // Create multiple old warnings (all > 90 days ago)
                $offsets = [];
                for ($i = 0; $i < $oldWarningCount; $i++) {
                    $offsets[] = $baseDaysAgo + $i; // spread them out over consecutive days
                }
                $this->createWarningsAtOffsets($user, $moderator, $offsets);

                // Issue a new warning today (only 1 within 90 days)
                $this->moderationService->issueWarning(
                    $user->id,
                    'New warning - old ones should not count',
                    $moderator->id,
                    '127.0.0.1'
                );

                // Refresh user from database
                $user->refresh();

                // INVARIANT: User must NOT be suspended (only 1 warning within 90 days)
                $this->assertFalse(
                    $user->is_suspended,
                    "User with {$oldWarningCount} old warnings (>{$baseDaysAgo}d ago) + 1 new " .
                    "should NOT be suspended. Only warnings within 90 days count."
                );
                $this->assertNull(
                    $user->suspended_until,
                    "suspended_until must remain null when only 1 recent warning exists"
                );

                Carbon::setTestNow();
            });
    }

    /**
     * Property: Mixed old and recent warnings - suspension triggers iff recent count (including new) >= 3.
     *
     * Generate users with a mix of old (>90 days) and recent (<=90 days) warnings.
     * Issue a new warning and verify suspension triggers only when recent count reaches 3.
     */
    public function testMixedOldAndRecentWarningsEscalation(): void
    {
        $this->forAll(
            Generators::choose(1, 4),   // number of old warnings (>90 days)
            Generators::choose(0, 1),   // number of recent warnings within 90 days (0 or 1)
            Generators::choose(91, 200), // days ago for old warnings
            Generators::choose(1, 89)   // days ago for recent warnings
        )
            ->then(function (int $oldCount, int $recentCount, int $oldDaysAgo, int $recentDaysAgo) {
                // Clean up from previous iterations
                Warning::query()->delete();

                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create([
                    'is_suspended' => false,
                    'suspended_until' => null,
                ]);
                $moderator = User::factory()->moderator()->create();

                // Create old warnings (> 90 days ago)
                $oldOffsets = [];
                for ($i = 0; $i < $oldCount; $i++) {
                    $oldOffsets[] = $oldDaysAgo + $i;
                }
                $this->createWarningsAtOffsets($user, $moderator, $oldOffsets);

                // Create recent warnings (within 90 days)
                if ($recentCount > 0) {
                    $this->createWarningsAtOffsets($user, $moderator, [$recentDaysAgo]);
                }

                // Issue a new warning today
                $this->moderationService->issueWarning(
                    $user->id,
                    'New warning - mixed history test',
                    $moderator->id,
                    '127.0.0.1'
                );

                // Refresh user from database
                $user->refresh();

                // Total recent warnings = recentCount + 1 (the new one)
                $totalRecent = $recentCount + 1;

                if ($totalRecent >= 3) {
                    // Should be suspended
                    $this->assertTrue(
                        $user->is_suspended,
                        "User with {$totalRecent} warnings within 90 days (>= 3) must be suspended"
                    );
                } else {
                    // Should NOT be suspended
                    $this->assertFalse(
                        $user->is_suspended,
                        "User with {$totalRecent} warnings within 90 days (< 3) must NOT be suspended. " .
                        "({$oldCount} old + {$recentCount} recent + 1 new = {$totalRecent} recent total)"
                    );
                    $this->assertNull($user->suspended_until);
                }

                Carbon::setTestNow();
            });
    }

    /**
     * Property: Exact boundary - warnings at exactly 90 days ago should still count
     * (within 90 days means created_at >= now - 90 days).
     *
     * Generate scenarios where existing warnings are at exactly 89 days (inside) and 91 days (outside).
     */
    public function testBoundaryAt90DaysWarnings(): void
    {
        $this->forAll(
            Generators::choose(1, 10000) // seed for slight variation
        )
            ->then(function (int $seed) {
                // Clean up from previous iterations
                Warning::query()->delete();

                Carbon::setTestNow(Carbon::now());

                // Test case: 2 warnings at exactly 89 days ago (within 90 days) + new = 3 → suspend
                $user1 = User::factory()->member()->create([
                    'is_suspended' => false,
                    'suspended_until' => null,
                ]);
                $moderator = User::factory()->moderator()->create();

                // Warnings at 89 days ago (still within 90-day window)
                $this->createWarningsAtOffsets($user1, $moderator, [89, 89]);

                $this->moderationService->issueWarning(
                    $user1->id,
                    'Boundary test - within window',
                    $moderator->id,
                    '127.0.0.1'
                );

                $user1->refresh();
                $this->assertTrue(
                    $user1->is_suspended,
                    "Warnings at 89 days ago are within 90-day window; 3 total should trigger suspension"
                );

                // Test case: 2 warnings at exactly 91 days ago (outside 90 days) + new = 1 recent → no suspend
                $user2 = User::factory()->member()->create([
                    'is_suspended' => false,
                    'suspended_until' => null,
                ]);

                // Warnings at 91 days ago (outside 90-day window)
                $this->createWarningsAtOffsets($user2, $moderator, [91, 91]);

                $this->moderationService->issueWarning(
                    $user2->id,
                    'Boundary test - outside window',
                    $moderator->id,
                    '127.0.0.1'
                );

                $user2->refresh();
                $this->assertFalse(
                    $user2->is_suspended,
                    "Warnings at 91 days ago are outside 90-day window; only 1 recent should NOT trigger suspension"
                );

                Carbon::setTestNow();
            });
    }
}
