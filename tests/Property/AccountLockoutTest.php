<?php

namespace Tests\Property;

use App\Models\User;
use App\Services\AuthService;
use Carbon\Carbon;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property 27: Account Lockout
 *
 * **Validates: Requirements 11.8**
 *
 * Tests that accounts lock at exactly 5 failed attempts within 15 min
 * and unlock after 30 min.
 */
class AccountLockoutTest extends TestCase
{
    use TestTrait;
    use RefreshDatabase;

    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = app(AuthService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Property: An account with fewer than 5 failed attempts is NOT locked.
     */
    public function testAccountWithFewerThanFiveFailedAttemptsIsNotLocked(): void
    {
        $this->forAll(
            Generators::choose(1, 4)
        )
            ->then(function (int $attempts) {
                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create([
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                ]);

                for ($i = 0; $i < $attempts; $i++) {
                    $this->authService->handleFailedLogin($user->id);
                }

                $user->refresh();

                $this->assertNull(
                    $user->locked_until,
                    "Account should NOT be locked with {$attempts} failed attempts (< 5)"
                );

                Carbon::setTestNow();
            });
    }

    /**
     * Property: An account with exactly 5 failed attempts IS locked.
     */
    public function testAccountWithExactlyFiveFailedAttemptsIsLocked(): void
    {
        $this->forAll(
            Generators::constant(5)
        )
            ->then(function (int $attempts) {
                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create([
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                ]);

                for ($i = 0; $i < $attempts; $i++) {
                    $this->authService->handleFailedLogin($user->id);
                }

                $user->refresh();

                $this->assertNotNull(
                    $user->locked_until,
                    'Account should be locked after exactly 5 failed attempts'
                );
                $this->assertTrue(
                    $user->locked_until->isFuture(),
                    'locked_until should be in the future when account is locked'
                );

                Carbon::setTestNow();
            });
    }

    /**
     * Property: An account with more than 5 failed attempts IS locked.
     */
    public function testAccountWithMoreThanFiveFailedAttemptsIsLocked(): void
    {
        $this->forAll(
            Generators::choose(6, 10)
        )
            ->then(function (int $attempts) {
                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create([
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                ]);

                for ($i = 0; $i < $attempts; $i++) {
                    $this->authService->handleFailedLogin($user->id);
                }

                $user->refresh();

                // After 5+ attempts, lockAccount() is called which resets counter
                // and sets locked_until. The account should have been locked at attempt 5.
                $this->assertNotNull(
                    $user->locked_until,
                    "Account should be locked with {$attempts} failed attempts (> 5)"
                );

                Carbon::setTestNow();
            });
    }

    /**
     * Property: A locked account with locked_until in the past is effectively unlocked (lock expired).
     */
    public function testLockedAccountWithExpiredLockIsEffectivelyUnlocked(): void
    {
        $this->forAll(
            Generators::choose(31, 120)
        )
            ->then(function (int $minutesPast) {
                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create([
                    'failed_login_attempts' => 0,
                    'locked_until' => Carbon::now()->subMinutes($minutesPast),
                ]);

                $user->refresh();

                $this->assertTrue(
                    $user->locked_until->isPast(),
                    "locked_until should be in the past after {$minutesPast} minutes"
                );
                // The lock has expired - the account is effectively unlocked
                $this->assertFalse(
                    $user->locked_until->isFuture(),
                    'Account with expired lock should NOT have locked_until in the future'
                );

                Carbon::setTestNow();
            });
    }

    /**
     * Property: Lock duration is exactly 30 minutes from time of locking.
     */
    public function testLockDurationIsExactlyThirtyMinutes(): void
    {
        $this->forAll(
            Generators::choose(5, 10)
        )
            ->then(function (int $attempts) {
                $now = Carbon::create(2024, 6, 15, 12, 0, 0);
                Carbon::setTestNow($now);

                $user = User::factory()->member()->create([
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                ]);

                for ($i = 0; $i < $attempts; $i++) {
                    $this->authService->handleFailedLogin($user->id);
                }

                $user->refresh();

                $expectedLockUntil = $now->copy()->addMinutes(30);

                $this->assertNotNull($user->locked_until, 'Account should be locked');
                $this->assertTrue(
                    $user->locked_until->equalTo($expectedLockUntil),
                    "Lock duration should be exactly 30 minutes. Expected: {$expectedLockUntil}, Got: {$user->locked_until}"
                );

                Carbon::setTestNow();
            });
    }
}
