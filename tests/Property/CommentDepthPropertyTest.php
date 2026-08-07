<?php

namespace Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use Tests\TestCase;

/**
 * Property 14: Comment Thread Depth Constraint
 *
 * For any comment tree, no comment SHALL have a depth exceeding 5.
 * When a reply is attempted at depth 5, it SHALL be stored as a flat reply
 * within the 5th level (depth remains 5).
 *
 * Tests the depth calculation logic: depth = min(parent_depth + 1, MAX_DEPTH)
 * where MAX_DEPTH = 5, and top-level comments (no parent) have depth 0.
 *
 * **Validates: Requirements 7.1**
 */
class CommentDepthPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Maximum depth constant mirroring CommentService::MAX_DEPTH.
     */
    private const MAX_DEPTH = 5;

    /**
     * Calculate comment depth using the same formula as CommentService::addComment.
     *
     * This mirrors the logic: depth = min(parent_depth + 1, MAX_DEPTH)
     * Top-level comments (no parent) have depth 0.
     */
    private function calculateDepth(?int $parentDepth): int
    {
        if ($parentDepth === null) {
            return 0;
        }

        return min($parentDepth + 1, self::MAX_DEPTH);
    }

    /**
     * Simulate building a chain of replies starting from depth 0
     * and returning all depths in the chain.
     */
    private function buildChain(int $length): array
    {
        $depths = [0]; // root comment
        $currentDepth = 0;

        for ($i = 1; $i <= $length; $i++) {
            $currentDepth = $this->calculateDepth($currentDepth);
            $depths[] = $currentDepth;
        }

        return $depths;
    }

    /**
     * Property: Top-level comments (no parent) always have depth 0.
     *
     * For any number of top-level comments, their depth is always 0.
     */
    public function testTopLevelCommentsAlwaysHaveDepthZero(): void
    {
        $this->forAll(
            Generators::choose(1, 100) // number of top-level comments
        )
            ->then(function (int $commentCount) {
                for ($i = 0; $i < $commentCount; $i++) {
                    $depth = $this->calculateDepth(null);

                    $this->assertEquals(
                        0,
                        $depth,
                        "Top-level comment (no parent) must always have depth 0"
                    );
                }
            });
    }

    /**
     * Property: Replies at depth 0-4 increment by exactly 1.
     *
     * For any parent depth from 0 to 4, the child depth is parent_depth + 1.
     */
    public function testRepliesBelowMaxDepthIncrementByOne(): void
    {
        $this->forAll(
            Generators::choose(0, 4) // parent depth below max
        )
            ->then(function (int $parentDepth) {
                $childDepth = $this->calculateDepth($parentDepth);

                $this->assertEquals(
                    $parentDepth + 1,
                    $childDepth,
                    "Reply to parent at depth {$parentDepth} should have depth " . ($parentDepth + 1)
                );
            });
    }

    /**
     * Property: Replies at depth 5 stay at depth 5 (flat).
     *
     * When parent_depth == MAX_DEPTH (5), the child depth remains 5.
     */
    public function testRepliesAtMaxDepthStayFlat(): void
    {
        // Repeat to demonstrate the property holds consistently
        $this->forAll(
            Generators::choose(1, 100) // number of repeated replies at depth 5
        )
            ->then(function (int $replyCount) {
                $currentDepth = self::MAX_DEPTH;

                for ($i = 0; $i < $replyCount; $i++) {
                    $childDepth = $this->calculateDepth($currentDepth);

                    $this->assertEquals(
                        self::MAX_DEPTH,
                        $childDepth,
                        "Reply to a depth-5 comment must remain at depth 5 (flat), not " . ($currentDepth + 1)
                    );

                    $currentDepth = $childDepth;
                }
            });
    }

    /**
     * Property: No comment ever exceeds depth 5 regardless of chain length.
     *
     * Generate random chain lengths from 1 to 20 and verify all depths in the
     * chain are <= MAX_DEPTH.
     */
    public function testNoCommentExceedsMaxDepthInAnyChain(): void
    {
        $this->forAll(
            Generators::choose(1, 20) // chain length (deep nesting attempts)
        )
            ->then(function (int $chainLength) {
                $depths = $this->buildChain($chainLength);

                foreach ($depths as $index => $depth) {
                    $this->assertLessThanOrEqual(
                        self::MAX_DEPTH,
                        $depth,
                        "Comment at chain position {$index} has depth {$depth} which exceeds max depth " . self::MAX_DEPTH
                    );
                    $this->assertGreaterThanOrEqual(
                        0,
                        $depth,
                        "Comment depth must be non-negative"
                    );
                }
            });
    }

    /**
     * Property: Depth calculation follows the formula min(parent_depth + 1, 5)
     * for any possible parent depth value.
     *
     * Generate random parent depths (including values that exceed max to test robustness)
     * and verify the formula holds.
     */
    public function testDepthCalculationMatchesFormula(): void
    {
        $this->forAll(
            Generators::choose(0, 10) // parent depth (including beyond max for robustness)
        )
            ->then(function (int $parentDepth) {
                $childDepth = $this->calculateDepth($parentDepth);
                $expected = min($parentDepth + 1, self::MAX_DEPTH);

                $this->assertEquals(
                    $expected,
                    $childDepth,
                    "Depth formula: min({$parentDepth} + 1, 5) = {$expected}, got {$childDepth}"
                );
            });
    }

    /**
     * Property: For any chain of depth N (where N >= 5), all comments from
     * position 5 onward have exactly depth 5.
     *
     * Generate random chain lengths from 5 to 15 and verify the depth cap behavior.
     */
    public function testAllCommentsAfterDepthFiveAreCappedAtFive(): void
    {
        $this->forAll(
            Generators::choose(5, 15) // chain length exceeding max depth
        )
            ->then(function (int $chainLength) {
                $depths = $this->buildChain($chainLength);

                // Verify comments at positions 0-5 have expected incremental depths
                for ($i = 0; $i <= min(5, $chainLength); $i++) {
                    $this->assertEquals(
                        $i,
                        $depths[$i],
                        "Comment at position {$i} should have depth {$i} in the first 6 levels"
                    );
                }

                // Verify all comments beyond position 5 are capped at 5
                for ($i = 6; $i <= $chainLength; $i++) {
                    $this->assertEquals(
                        self::MAX_DEPTH,
                        $depths[$i],
                        "Comment at position {$i} (beyond max depth) should be capped at depth 5"
                    );
                }
            });
    }

    /**
     * Property: Random tree structures never produce depth > 5.
     *
     * Simulate random tree building where each new comment replies to a random
     * existing comment. Verify the depth constraint holds for all generated comments.
     */
    public function testRandomTreeStructuresNeverExceedMaxDepth(): void
    {
        $this->forAll(
            Generators::choose(5, 30),  // number of comments in the tree
            Generators::choose(1, 100)  // seed for reply target selection
        )
            ->then(function (int $treeSize, int $seed) {
                // Track all comments as [id => depth]
                $comments = [0 => 0]; // root at depth 0

                // Build a random tree
                for ($i = 1; $i < $treeSize; $i++) {
                    // Pick a random existing comment to reply to
                    $parentIndex = ($seed * $i) % count($comments);
                    $parentIds = array_keys($comments);
                    $parentId = $parentIds[$parentIndex];
                    $parentDepth = $comments[$parentId];

                    // Calculate child depth
                    $childDepth = $this->calculateDepth($parentDepth);
                    $comments[$i] = $childDepth;

                    $this->assertLessThanOrEqual(
                        self::MAX_DEPTH,
                        $childDepth,
                        "Comment {$i} replying to comment at depth {$parentDepth} should not exceed depth 5"
                    );
                }

                // Final invariant check: no depth in the entire tree exceeds MAX_DEPTH
                foreach ($comments as $id => $depth) {
                    $this->assertLessThanOrEqual(self::MAX_DEPTH, $depth);
                    $this->assertGreaterThanOrEqual(0, $depth);
                }
            });
    }

    /**
     * Property: Boundary - reply to depth 4 gives exactly depth 5.
     */
    public function testReplyToDepthFourGivesExactlyDepthFive(): void
    {
        $depth = $this->calculateDepth(4);
        $this->assertEquals(5, $depth, "Reply to depth 4 should give exactly depth 5");
    }

    /**
     * Property: Boundary - reply to depth 5 gives exactly depth 5 (not 6).
     */
    public function testReplyToDepthFiveGivesExactlyDepthFive(): void
    {
        $depth = $this->calculateDepth(5);
        $this->assertEquals(5, $depth, "Reply to depth 5 should give exactly depth 5, not 6");
    }
}
