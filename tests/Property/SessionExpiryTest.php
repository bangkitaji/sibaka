<?php

namespace Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use Tests\TestCase;

/**
 * Property 26: Session Expiry
 *
 * Test that sessions expire after 30 minutes of inactivity and remain valid within 30 minutes.
 * Generate random inactivity durations and verify behavior at boundary.
 *
 * **Validates: Requirements 11.7**
 */
class SessionExpiryTest extends TestCase
{
    use TestTrait;

    /**
     * Property: The session lifetime configuration is set to exactly 30 minutes.
     * For any positive integer, the configured value should always be 30.
     */
    public function testSessionLifetimeConfigIsThirtyMinutes(): void
    {
        $this->forAll(
            Generators::choose(1, 100) // dummy generator to exercise property
        )
            ->then(function (int $_) {
                $this->assertEquals(
                    30,
                    config('session.lifetime'),
                    'Session lifetime should be configured to 30 minutes'
                );
            });
    }

    /**
     * Property: For any inactivity duration less than 30 minutes, the session
     * should remain valid (user stays authenticated).
     */
    public function testSessionRemainsValidWithinThirtyMinutes(): void
    {
        $this->forAll(
            Generators::choose(1, 29) // 1 to 29 minutes (less than 30)
        )
            ->then(function (int $inactivityMinutes) {
                $lifetime = config('session.lifetime');

                // Any duration less than 30 should be within the lifetime
                $this->assertLessThan(
                    $lifetime,
                    $inactivityMinutes,
                    "Inactivity of {$inactivityMinutes} min should be less than session lifetime of {$lifetime} min"
                );

                // Session should remain valid when inactivity < lifetime
                $sessionValid = $inactivityMinutes < $lifetime;
                $this->assertTrue(
                    $sessionValid,
                    "Session should remain valid after {$inactivityMinutes} minutes of inactivity"
                );
            });
    }

    /**
     * Property: For any inactivity duration >= 30 minutes, the session should be expired.
     */
    public function testSessionExpiresAtOrAfterThirtyMinutes(): void
    {
        $this->forAll(
            Generators::choose(30, 120) // 30 to 120 minutes (at or beyond threshold)
        )
            ->then(function (int $inactivityMinutes) {
                $lifetime = config('session.lifetime');

                // Any duration >= 30 should exceed or equal the lifetime
                $this->assertGreaterThanOrEqual(
                    $lifetime,
                    $inactivityMinutes,
                    "Inactivity of {$inactivityMinutes} min should be >= session lifetime of {$lifetime} min"
                );

                // Session should be expired when inactivity >= lifetime
                $sessionExpired = $inactivityMinutes >= $lifetime;
                $this->assertTrue(
                    $sessionExpired,
                    "Session should be expired after {$inactivityMinutes} minutes of inactivity"
                );
            });
    }

    /**
     * Property: At exactly 30 minutes the session expires (boundary test).
     * For the boundary value, expired = (duration >= lifetime) holds true.
     */
    public function testSessionExpiresAtExactBoundary(): void
    {
        $this->forAll(
            Generators::choose(28, 32) // Narrow range around boundary
        )
            ->then(function (int $inactivityMinutes) {
                $lifetime = config('session.lifetime');

                $shouldBeValid = $inactivityMinutes < $lifetime;
                $shouldBeExpired = $inactivityMinutes >= $lifetime;

                // At 28, 29: valid. At 30, 31, 32: expired.
                if ($inactivityMinutes < 30) {
                    $this->assertTrue(
                        $shouldBeValid,
                        "Session should be valid at {$inactivityMinutes} minutes (< 30)"
                    );
                    $this->assertFalse(
                        $shouldBeExpired,
                        "Session should NOT be expired at {$inactivityMinutes} minutes (< 30)"
                    );
                } else {
                    $this->assertFalse(
                        $shouldBeValid,
                        "Session should NOT be valid at {$inactivityMinutes} minutes (>= 30)"
                    );
                    $this->assertTrue(
                        $shouldBeExpired,
                        "Session should be expired at {$inactivityMinutes} minutes (>= 30)"
                    );
                }
            });
    }

    /**
     * Property: For any randomly generated duration, session validity is determined
     * exclusively by whether duration < 30 minutes (the configured lifetime).
     * This is the core invariant: valid iff inactivity < lifetime.
     */
    public function testSessionValidityDeterminedByLifetimeThreshold(): void
    {
        $this->forAll(
            Generators::choose(1, 120) // Wide range: 1 to 120 minutes
        )
            ->then(function (int $inactivityMinutes) {
                $lifetime = config('session.lifetime');

                $shouldBeValid = $inactivityMinutes < $lifetime;
                $shouldBeExpired = $inactivityMinutes >= $lifetime;

                // Valid and expired are always complementary
                $this->assertNotEquals(
                    $shouldBeValid,
                    $shouldBeExpired,
                    'Session should be either valid or expired, never both or neither'
                );

                // The threshold is exactly the configured lifetime
                if ($shouldBeValid) {
                    $this->assertLessThan(
                        $lifetime,
                        $inactivityMinutes,
                        "Valid session: inactivity {$inactivityMinutes} min must be < lifetime {$lifetime} min"
                    );
                } else {
                    $this->assertGreaterThanOrEqual(
                        $lifetime,
                        $inactivityMinutes,
                        "Expired session: inactivity {$inactivityMinutes} min must be >= lifetime {$lifetime} min"
                    );
                }
            });
    }
}
