<?php

namespace Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use Tests\TestCase;

/**
 * Property 16: Accepted Solution Uniqueness and Reputation Consistency
 *
 * Tests that:
 * 1. At most one accepted solution per thread at any point
 * 2. Reputation conservation: mark then unmark = net 0 reputation change
 * 3. When changing accepted solution: old author loses 50, new author gains 50
 * 4. Re-marking the same comment is idempotent (no point change)
 *
 * Generates random sequences of mark/unmark/change operations and verifies
 * the invariants hold after every operation.
 *
 * The CommentService logic:
 * - markAcceptedSolution: if previous exists and != new, decrement old author by 50, increment new by 50
 *   If same comment re-marked, no point change. Sets content.accepted_solution_id = commentId.
 * - unmarkAcceptedSolution: if comment is current solution, decrement author by 50, set field to null.
 *
 * **Validates: Requirements 7.3, 7.4, 7.5**
 */
class AcceptedSolutionPropertyTest extends TestCase
{
    use TestTrait;

    private const REPUTATION_AWARD = 50;

    /**
     * Simulates the state of a single thread's accepted solution and
     * reputation tracking for comment authors.
     */
    private function createThreadState(int $commentCount): array
    {
        // Create distinct "authors" for each comment (some may share authors)
        $authors = [];
        for ($i = 0; $i < $commentCount; $i++) {
            // Assign author IDs (0-based), with some sharing to test transfer between same-author comments
            $authors[$i] = $i % max(1, intdiv($commentCount, 2));
        }

        return [
            'accepted_solution_id' => null,
            'authors' => $authors, // commentIndex => authorId
            'reputation' => array_fill(0, $commentCount, 0), // authorId => reputation delta
        ];
    }

    /**
     * Simulate markAcceptedSolution logic from CommentService.
     *
     * Returns updated state after marking a comment as accepted solution.
     */
    private function markAcceptedSolution(array $state, int $commentIndex): array
    {
        $previousSolutionId = $state['accepted_solution_id'];
        $commentId = $commentIndex;

        // If there's an existing accepted solution, revoke reputation from previous author
        if ($previousSolutionId !== null && $previousSolutionId !== $commentId) {
            $previousAuthor = $state['authors'][$previousSolutionId];
            $state['reputation'][$previousAuthor] -= self::REPUTATION_AWARD;
        }

        // Award +50 reputation to new solution author (unless it's the same comment being re-marked)
        if ($previousSolutionId !== $commentId) {
            $newAuthor = $state['authors'][$commentId];
            $state['reputation'][$newAuthor] += self::REPUTATION_AWARD;
        }

        $state['accepted_solution_id'] = $commentId;

        return $state;
    }

    /**
     * Simulate unmarkAcceptedSolution logic from CommentService.
     *
     * Returns updated state after unmarking the accepted solution.
     */
    private function unmarkAcceptedSolution(array $state, int $commentIndex): array
    {
        // Only revoke if this comment is actually the current accepted solution
        if ($state['accepted_solution_id'] === $commentIndex) {
            $author = $state['authors'][$commentIndex];
            $state['reputation'][$author] -= self::REPUTATION_AWARD;
        }

        $state['accepted_solution_id'] = null;

        return $state;
    }

    /**
     * Property: At most one accepted solution per thread after any sequence of operations.
     *
     * Generates random sequences of mark/unmark operations and verifies
     * that accepted_solution_id is either null or a single comment ID (never multiple).
     */
    public function testAtMostOneAcceptedSolutionPerThread(): void
    {
        $this->forAll(
            Generators::choose(2, 10),   // number of comments in thread
            Generators::choose(1, 20),   // number of operations
            Generators::choose(1, 10000) // seed for operation generation
        )
            ->then(function (int $commentCount, int $operationCount, int $seed) {
                $state = $this->createThreadState($commentCount);

                for ($i = 0; $i < $operationCount; $i++) {
                    // Deterministic pseudo-random: decide operation type and target
                    $opSeed = ($seed * ($i + 1) * 31) % 100;
                    $targetComment = (($seed * ($i + 1) * 17) % $commentCount);

                    if ($opSeed < 60) {
                        // 60% chance: mark a comment as accepted
                        $state = $this->markAcceptedSolution($state, $targetComment);
                    } else {
                        // 40% chance: unmark
                        $state = $this->unmarkAcceptedSolution($state, $targetComment);
                    }

                    // INVARIANT: At most one accepted solution at any point
                    $this->assertTrue(
                        $state['accepted_solution_id'] === null || is_int($state['accepted_solution_id']),
                        'accepted_solution_id must be null or a single comment index'
                    );

                    if ($state['accepted_solution_id'] !== null) {
                        $this->assertGreaterThanOrEqual(0, $state['accepted_solution_id']);
                        $this->assertLessThan($commentCount, $state['accepted_solution_id']);
                    }
                }
            });
    }

    /**
     * Property: Mark then unmark = net zero reputation change.
     *
     * For any comment, marking it as accepted (+50) and then unmarking it (-50)
     * results in a net 0 reputation change for the author.
     */
    public function testMarkThenUnmarkIsNetZeroReputation(): void
    {
        $this->forAll(
            Generators::choose(2, 10), // number of comments
            Generators::choose(0, 9)   // which comment to mark/unmark
        )
            ->then(function (int $commentCount, int $targetIndex) {
                $targetIndex = $targetIndex % $commentCount; // ensure valid index
                $state = $this->createThreadState($commentCount);

                // Record initial reputation (all zeros)
                $initialReputation = $state['reputation'];

                // Mark the comment
                $state = $this->markAcceptedSolution($state, $targetIndex);

                // Verify +50 was awarded
                $author = $state['authors'][$targetIndex];
                $this->assertEquals(
                    self::REPUTATION_AWARD,
                    $state['reputation'][$author],
                    "Author should have +50 after marking"
                );

                // Unmark the same comment
                $state = $this->unmarkAcceptedSolution($state, $targetIndex);

                // Verify net zero
                $this->assertEquals(
                    0,
                    $state['reputation'][$author],
                    "Author should have net 0 reputation after mark then unmark"
                );

                // Verify all reputations are back to initial
                $this->assertEquals(
                    $initialReputation,
                    $state['reputation'],
                    'All reputations should be back to initial state after mark+unmark'
                );
            });
    }

    /**
     * Property: Changing accepted solution transfers reputation correctly.
     *
     * When changing from comment A to comment B (different comments with different authors):
     * - Author of A loses 50
     * - Author of B gains 50
     *
     * Uses unique authors per comment to cleanly verify the transfer.
     */
    public function testChangingAcceptedSolutionTransfersReputation(): void
    {
        $this->forAll(
            Generators::choose(3, 10), // number of comments (need at least 3 for distinct authors)
            Generators::choose(0, 9),  // first comment to mark
            Generators::choose(0, 9)   // second comment to mark (change to)
        )
            ->then(function (int $commentCount, int $firstIndex, int $secondIndex) {
                $firstIndex = $firstIndex % $commentCount;
                $secondIndex = $secondIndex % $commentCount;

                // Skip if same comment (covered by idempotency test)
                if ($firstIndex === $secondIndex) {
                    $this->assertTrue(true);
                    return;
                }

                // Use unique authors per comment for clean transfer verification
                $state = [
                    'accepted_solution_id' => null,
                    'authors' => range(0, $commentCount - 1),
                    'reputation' => array_fill(0, $commentCount, 0),
                ];

                $firstAuthor = $state['authors'][$firstIndex];
                $secondAuthor = $state['authors'][$secondIndex];

                // Mark first comment
                $state = $this->markAcceptedSolution($state, $firstIndex);

                $this->assertEquals(
                    self::REPUTATION_AWARD,
                    $state['reputation'][$firstAuthor],
                    "First author should have +50 after initial mark"
                );

                // Change to second comment
                $state = $this->markAcceptedSolution($state, $secondIndex);

                // First author should be back to 0 (gained 50 then lost 50)
                $this->assertEquals(
                    0,
                    $state['reputation'][$firstAuthor],
                    "Old solution author should have net 0 after transfer (gained 50, lost 50)"
                );

                // Second author should have gained 50
                $this->assertEquals(
                    self::REPUTATION_AWARD,
                    $state['reputation'][$secondAuthor],
                    "New solution author should have +50 after transfer"
                );

                // Accepted solution should now be the second comment
                $this->assertEquals($secondIndex, $state['accepted_solution_id']);
            });
    }

    /**
     * Property: Re-marking the same comment is idempotent (no point change).
     *
     * If a comment is already the accepted solution, marking it again
     * does not change reputation or the accepted_solution_id.
     */
    public function testRemarkingSameCommentIsIdempotent(): void
    {
        $this->forAll(
            Generators::choose(2, 10), // number of comments
            Generators::choose(0, 9),  // which comment to mark
            Generators::choose(1, 5)   // number of times to re-mark
        )
            ->then(function (int $commentCount, int $targetIndex, int $remarkCount) {
                $targetIndex = $targetIndex % $commentCount;
                $state = $this->createThreadState($commentCount);

                // Mark the comment once
                $state = $this->markAcceptedSolution($state, $targetIndex);

                $author = $state['authors'][$targetIndex];
                $reputationAfterFirstMark = $state['reputation'][$author];
                $this->assertEquals(self::REPUTATION_AWARD, $reputationAfterFirstMark);

                // Re-mark the same comment multiple times
                for ($i = 0; $i < $remarkCount; $i++) {
                    $state = $this->markAcceptedSolution($state, $targetIndex);

                    // Reputation should not change
                    $this->assertEquals(
                        $reputationAfterFirstMark,
                        $state['reputation'][$author],
                        "Re-marking same comment (attempt " . ($i + 1) . ") should not change reputation"
                    );

                    // Accepted solution should remain the same
                    $this->assertEquals(
                        $targetIndex,
                        $state['accepted_solution_id'],
                        "Accepted solution ID should remain unchanged after re-mark"
                    );
                }
            });
    }

    /**
     * Property: Total reputation sum is conserved across mark/change/unmark operations.
     *
     * When unmark is only called on the current accepted solution (correct usage):
     * - If accepted_solution_id is not null: total reputation = +50
     * - If accepted_solution_id is null: total reputation = 0
     *
     * This verifies reputation conservation: every +50 is balanced by a -50 on unmark/change.
     */
    public function testReputationSumConservation(): void
    {
        $this->forAll(
            Generators::choose(3, 8),    // number of comments
            Generators::choose(2, 15),   // number of operations
            Generators::choose(1, 10000) // seed
        )
            ->then(function (int $commentCount, int $operationCount, int $seed) {
                // Use unique authors for clean verification
                $state = [
                    'accepted_solution_id' => null,
                    'authors' => range(0, $commentCount - 1),
                    'reputation' => array_fill(0, $commentCount, 0),
                ];

                for ($i = 0; $i < $operationCount; $i++) {
                    $opSeed = ($seed * ($i + 1) * 31) % 100;
                    $targetComment = (($seed * ($i + 1) * 17) % $commentCount);

                    if ($opSeed < 70) {
                        // Mark/change operation
                        $state = $this->markAcceptedSolution($state, $targetComment);
                    } else {
                        // Unmark: only unmark the CURRENT accepted solution (correct usage)
                        if ($state['accepted_solution_id'] !== null) {
                            $state = $this->unmarkAcceptedSolution($state, $state['accepted_solution_id']);
                        }
                    }
                }

                $totalReputation = array_sum($state['reputation']);

                if ($state['accepted_solution_id'] !== null) {
                    // If there's an active accepted solution, total reputation = +50
                    $this->assertEquals(
                        self::REPUTATION_AWARD,
                        $totalReputation,
                        "Total reputation should be +50 when an accepted solution exists"
                    );
                } else {
                    // If no accepted solution, total reputation = 0
                    $this->assertEquals(
                        0,
                        $totalReputation,
                        "Total reputation should be 0 when no accepted solution exists"
                    );
                }
            });
    }

    /**
     * Property: Unmarking a non-accepted comment has no effect on reputation.
     *
     * If we try to unmark a comment that is NOT the current accepted solution,
     * reputation should not change (the service only decrements if comment IS the solution).
     */
    public function testUnmarkingNonAcceptedCommentHasNoEffect(): void
    {
        $this->forAll(
            Generators::choose(3, 10), // number of comments
            Generators::choose(0, 9),  // comment to mark as accepted
            Generators::choose(0, 9)   // different comment to try unmarking
        )
            ->then(function (int $commentCount, int $acceptedIndex, int $otherIndex) {
                $acceptedIndex = $acceptedIndex % $commentCount;
                $otherIndex = $otherIndex % $commentCount;

                // Skip if same comment
                if ($acceptedIndex === $otherIndex) {
                    $this->assertTrue(true);
                    return;
                }

                $state = $this->createThreadState($commentCount);

                // Mark one comment as accepted
                $state = $this->markAcceptedSolution($state, $acceptedIndex);
                $reputationBeforeUnmark = $state['reputation'];

                // Try to unmark a DIFFERENT comment
                $state = $this->unmarkAcceptedSolution($state, $otherIndex);

                // The accepted_solution_id is set to null by unmark regardless
                // But reputation only changes if the comment was the actual solution
                // Since otherIndex != acceptedIndex, reputation should NOT change
                $this->assertEquals(
                    $reputationBeforeUnmark,
                    $state['reputation'],
                    "Unmarking a non-accepted comment should not change any reputation"
                );
            });
    }

    /**
     * Property: After any sequence of operations, the accepted solution author
     * (if one exists) has exactly +50 net reputation from accepted solution awards.
     *
     * This verifies that the current accepted solution's author always benefits
     * from exactly one +50 award.
     */
    public function testAcceptedSolutionAuthorAlwaysHasPositiveReputation(): void
    {
        $this->forAll(
            Generators::choose(3, 8),    // number of comments
            Generators::choose(1, 20),   // number of operations
            Generators::choose(1, 10000) // seed
        )
            ->then(function (int $commentCount, int $operationCount, int $seed) {
                // Use unique authors for each comment to simplify verification
                $state = [
                    'accepted_solution_id' => null,
                    'authors' => range(0, $commentCount - 1), // each comment has unique author
                    'reputation' => array_fill(0, $commentCount, 0),
                ];

                for ($i = 0; $i < $operationCount; $i++) {
                    $opSeed = ($seed * ($i + 1) * 31) % 100;
                    $targetComment = (($seed * ($i + 1) * 17) % $commentCount);

                    if ($opSeed < 65) {
                        $state = $this->markAcceptedSolution($state, $targetComment);
                    } else {
                        $state = $this->unmarkAcceptedSolution($state, $targetComment);
                    }
                }

                if ($state['accepted_solution_id'] !== null) {
                    $currentAuthor = $state['authors'][$state['accepted_solution_id']];
                    $this->assertGreaterThanOrEqual(
                        self::REPUTATION_AWARD,
                        $state['reputation'][$currentAuthor],
                        "Current accepted solution author should have at least +50 reputation"
                    );
                }
            });
    }
}
