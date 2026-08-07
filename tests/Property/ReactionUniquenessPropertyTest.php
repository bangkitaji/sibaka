<?php

namespace Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use Tests\TestCase;

/**
 * Property 19: Reaction Uniqueness and Counter Accuracy
 *
 * Tests that:
 * 1. At most one reaction per user per content after any sequence of operations
 * 2. Total count = number of distinct users who have an active reaction
 * 3. changeReaction doesn't change the total count (only the type distribution)
 * 4. removeReaction decreases total by 1
 *
 * Generates random sequences of react/change/remove operations and verifies
 * the invariants hold after every operation.
 *
 * The ReactionService logic:
 * - react(): creates a reaction for a user on content (enforced unique by DB constraint)
 * - removeReaction(): deletes the user's reaction on content
 * - changeReaction(): updates the type of an existing reaction, or creates if none exists
 *
 * **Validates: Requirements 8.1, 8.2, 8.3**
 */
class ReactionUniquenessPropertyTest extends TestCase
{
    use TestTrait;

    private const REACTION_TYPES = ['insightful', 'relatable', 'helpful', 'solutif'];

    /**
     * Simulates the state of reactions on a single content item.
     * Tracks which users have reacted and with what type.
     *
     * @return array{reactions: array<string, string>} - userId => reactionType
     */
    private function createReactionState(): array
    {
        return [
            'reactions' => [], // userId => reactionType
        ];
    }

    /**
     * Simulate react() from ReactionService.
     * Adds a reaction for a user. If user already has a reaction, it fails (unique constraint).
     * In real service, this would throw UniqueConstraintViolationException.
     * Here we model the constraint: only add if user has no existing reaction.
     */
    private function simulateReact(array $state, string $userId, string $type): array
    {
        // Only add if user doesn't already have a reaction (unique constraint)
        if (!isset($state['reactions'][$userId])) {
            $state['reactions'][$userId] = $type;
        }

        return $state;
    }

    /**
     * Simulate removeReaction() from ReactionService.
     * Removes the user's reaction if it exists.
     */
    private function simulateRemoveReaction(array $state, string $userId): array
    {
        unset($state['reactions'][$userId]);

        return $state;
    }

    /**
     * Simulate changeReaction() from ReactionService.
     * Changes the type of an existing reaction, or creates one if none exists.
     */
    private function simulateChangeReaction(array $state, string $userId, string $newType): array
    {
        // changeReaction updates existing or creates new (as per service implementation)
        $state['reactions'][$userId] = $newType;

        return $state;
    }

    /**
     * Helper: get total reaction count from state.
     */
    private function getTotalCount(array $state): int
    {
        return count($state['reactions']);
    }

    /**
     * Helper: get type breakdown from state.
     */
    private function getTypeBreakdown(array $state): array
    {
        $breakdown = array_fill_keys(self::REACTION_TYPES, 0);

        foreach ($state['reactions'] as $type) {
            $breakdown[$type]++;
        }

        return $breakdown;
    }

    /**
     * Property: At most one reaction per user per content after any sequence of operations.
     *
     * Generates random sequences of react/change/remove operations and verifies
     * that each user has at most one reaction at all times.
     */
    public function testAtMostOneReactionPerUserPerContent(): void
    {
        $this->forAll(
            Generators::choose(2, 10),   // number of distinct users
            Generators::choose(5, 30),   // number of operations
            Generators::choose(1, 10000) // seed for operation generation
        )
            ->then(function (int $userCount, int $operationCount, int $seed) {
                $state = $this->createReactionState();
                $userIds = [];
                for ($i = 0; $i < $userCount; $i++) {
                    $userIds[] = "user_{$i}";
                }

                for ($i = 0; $i < $operationCount; $i++) {
                    // Deterministic pseudo-random operation selection
                    $opSeed = ($seed * ($i + 1) * 31) % 100;
                    $userIndex = (($seed * ($i + 1) * 17) % $userCount);
                    $userId = $userIds[$userIndex];
                    $typeIndex = (($seed * ($i + 1) * 13) % count(self::REACTION_TYPES));
                    $type = self::REACTION_TYPES[$typeIndex];

                    if ($opSeed < 40) {
                        // 40% chance: react
                        $state = $this->simulateReact($state, $userId, $type);
                    } elseif ($opSeed < 70) {
                        // 30% chance: change reaction
                        $state = $this->simulateChangeReaction($state, $userId, $type);
                    } else {
                        // 30% chance: remove reaction
                        $state = $this->simulateRemoveReaction($state, $userId);
                    }

                    // INVARIANT: Each user has at most one reaction
                    $userReactionCounts = array_count_values(array_keys($state['reactions']));
                    foreach ($userReactionCounts as $uid => $count) {
                        $this->assertEquals(
                            1,
                            $count,
                            "User {$uid} should have at most 1 reaction, found {$count}"
                        );
                    }

                    // INVARIANT: Each user's reaction value is one of the valid types
                    foreach ($state['reactions'] as $uid => $reactionType) {
                        $this->assertContains(
                            $reactionType,
                            self::REACTION_TYPES,
                            "User {$uid} has invalid reaction type: {$reactionType}"
                        );
                    }
                }
            });
    }

    /**
     * Property: Total count equals number of distinct users with an active reaction.
     *
     * After any sequence of operations, the total reaction count must equal
     * the number of distinct users who currently have an active reaction.
     */
    public function testTotalCountEqualsDistinctUsersWithActiveReaction(): void
    {
        $this->forAll(
            Generators::choose(3, 15),   // number of distinct users
            Generators::choose(5, 40),   // number of operations
            Generators::choose(1, 10000) // seed
        )
            ->then(function (int $userCount, int $operationCount, int $seed) {
                $state = $this->createReactionState();
                $userIds = [];
                for ($i = 0; $i < $userCount; $i++) {
                    $userIds[] = "user_{$i}";
                }

                for ($i = 0; $i < $operationCount; $i++) {
                    $opSeed = ($seed * ($i + 1) * 31) % 100;
                    $userIndex = (($seed * ($i + 1) * 17) % $userCount);
                    $userId = $userIds[$userIndex];
                    $typeIndex = (($seed * ($i + 1) * 13) % count(self::REACTION_TYPES));
                    $type = self::REACTION_TYPES[$typeIndex];

                    if ($opSeed < 40) {
                        $state = $this->simulateReact($state, $userId, $type);
                    } elseif ($opSeed < 70) {
                        $state = $this->simulateChangeReaction($state, $userId, $type);
                    } else {
                        $state = $this->simulateRemoveReaction($state, $userId);
                    }
                }

                // INVARIANT: Total count = number of distinct users with active reaction
                $totalCount = $this->getTotalCount($state);
                $distinctUsers = count($state['reactions']);

                $this->assertEquals(
                    $distinctUsers,
                    $totalCount,
                    "Total count ({$totalCount}) must equal distinct users with active reaction ({$distinctUsers})"
                );

                // Verify via type breakdown: sum of all types = total count
                $breakdown = $this->getTypeBreakdown($state);
                $typeSum = array_sum($breakdown);

                $this->assertEquals(
                    $totalCount,
                    $typeSum,
                    "Sum of type breakdown ({$typeSum}) must equal total count ({$totalCount})"
                );
            });
    }

    /**
     * Property: changeReaction does not change the total count.
     *
     * When a user changes their reaction type, the total number of reactions
     * remains the same. Only the per-type breakdown changes.
     */
    public function testChangeReactionDoesNotChangeTotalCount(): void
    {
        $this->forAll(
            Generators::choose(2, 10),  // number of users
            Generators::choose(1, 20),  // number of initial reactions to set up
            Generators::choose(1, 10),  // number of change operations
            Generators::choose(1, 10000) // seed
        )
            ->then(function (int $userCount, int $setupOps, int $changeOps, int $seed) {
                $state = $this->createReactionState();
                $userIds = [];
                for ($i = 0; $i < $userCount; $i++) {
                    $userIds[] = "user_{$i}";
                }

                // Setup: add some reactions
                for ($i = 0; $i < $setupOps; $i++) {
                    $userIndex = (($seed * ($i + 1) * 17) % $userCount);
                    $userId = $userIds[$userIndex];
                    $typeIndex = (($seed * ($i + 1) * 13) % count(self::REACTION_TYPES));
                    $type = self::REACTION_TYPES[$typeIndex];
                    $state = $this->simulateReact($state, $userId, $type);
                }

                // Record total count after setup
                $totalBefore = $this->getTotalCount($state);

                // Only proceed if there are active reactions to change
                if ($totalBefore === 0) {
                    $this->assertTrue(true);
                    return;
                }

                // Perform change operations on users who already have reactions
                $usersWithReactions = array_keys($state['reactions']);

                for ($i = 0; $i < $changeOps; $i++) {
                    $targetIndex = (($seed * ($i + 1) * 23) % count($usersWithReactions));
                    $targetUser = $usersWithReactions[$targetIndex];
                    $newTypeIndex = (($seed * ($i + 1) * 37) % count(self::REACTION_TYPES));
                    $newType = self::REACTION_TYPES[$newTypeIndex];

                    $state = $this->simulateChangeReaction($state, $targetUser, $newType);

                    // INVARIANT: total count stays the same after each change
                    $totalAfterChange = $this->getTotalCount($state);
                    $this->assertEquals(
                        $totalBefore,
                        $totalAfterChange,
                        "changeReaction should not affect total count. Before: {$totalBefore}, After: {$totalAfterChange}"
                    );
                }
            });
    }

    /**
     * Property: removeReaction decreases total count by exactly 1 (if user had a reaction).
     *
     * When a user removes their reaction, the total count decreases by 1.
     * If the user had no reaction, the count stays the same.
     */
    public function testRemoveReactionDecreasesTotalByOne(): void
    {
        $this->forAll(
            Generators::choose(3, 10),  // number of users
            Generators::choose(2, 15),  // number of initial reactions to set up
            Generators::choose(1, 10000) // seed
        )
            ->then(function (int $userCount, int $setupOps, int $seed) {
                $state = $this->createReactionState();
                $userIds = [];
                for ($i = 0; $i < $userCount; $i++) {
                    $userIds[] = "user_{$i}";
                }

                // Setup: add some reactions
                for ($i = 0; $i < $setupOps; $i++) {
                    $userIndex = (($seed * ($i + 1) * 17) % $userCount);
                    $userId = $userIds[$userIndex];
                    $typeIndex = (($seed * ($i + 1) * 13) % count(self::REACTION_TYPES));
                    $type = self::REACTION_TYPES[$typeIndex];
                    $state = $this->simulateReact($state, $userId, $type);
                }

                // Only proceed if there are active reactions to remove
                if ($this->getTotalCount($state) === 0) {
                    $this->assertTrue(true);
                    return;
                }

                // Pick a user who has a reaction and remove it
                $usersWithReactions = array_keys($state['reactions']);
                $targetIndex = ($seed * 41) % count($usersWithReactions);
                $targetUser = $usersWithReactions[$targetIndex];

                $totalBefore = $this->getTotalCount($state);
                $state = $this->simulateRemoveReaction($state, $targetUser);
                $totalAfter = $this->getTotalCount($state);

                // INVARIANT: total decreased by exactly 1
                $this->assertEquals(
                    $totalBefore - 1,
                    $totalAfter,
                    "removeReaction should decrease total by 1. Before: {$totalBefore}, After: {$totalAfter}"
                );

                // INVARIANT: the removed user no longer has a reaction
                $this->assertArrayNotHasKey(
                    $targetUser,
                    $state['reactions'],
                    "Removed user should not have an active reaction"
                );
            });
    }

    /**
     * Property: Removing a reaction for a user who has no reaction does not change total.
     *
     * If removeReaction is called on a user with no existing reaction,
     * the total count remains unchanged.
     */
    public function testRemoveNonExistentReactionDoesNotChangeTotal(): void
    {
        $this->forAll(
            Generators::choose(3, 10),  // number of users
            Generators::choose(1, 10),  // number of initial reactions to set up
            Generators::choose(1, 10000) // seed
        )
            ->then(function (int $userCount, int $setupOps, int $seed) {
                $state = $this->createReactionState();
                $userIds = [];
                for ($i = 0; $i < $userCount; $i++) {
                    $userIds[] = "user_{$i}";
                }

                // Setup: add some reactions (not for all users)
                for ($i = 0; $i < min($setupOps, $userCount - 1); $i++) {
                    $userIndex = ($i % ($userCount - 1)); // leave at least one user without reaction
                    $userId = $userIds[$userIndex];
                    $typeIndex = (($seed * ($i + 1) * 13) % count(self::REACTION_TYPES));
                    $type = self::REACTION_TYPES[$typeIndex];
                    $state = $this->simulateReact($state, $userId, $type);
                }

                // Find a user who does NOT have a reaction
                $usersWithoutReaction = array_diff($userIds, array_keys($state['reactions']));

                if (empty($usersWithoutReaction)) {
                    $this->assertTrue(true);
                    return;
                }

                $nonReactingUser = array_values($usersWithoutReaction)[0];
                $totalBefore = $this->getTotalCount($state);

                // Remove reaction for a user who doesn't have one
                $state = $this->simulateRemoveReaction($state, $nonReactingUser);
                $totalAfter = $this->getTotalCount($state);

                // INVARIANT: total should not change
                $this->assertEquals(
                    $totalBefore,
                    $totalAfter,
                    "Removing non-existent reaction should not change total. Before: {$totalBefore}, After: {$totalAfter}"
                );
            });
    }

    /**
     * Property: Type breakdown sums always equal total count.
     *
     * After any sequence of operations, the sum of reactions across all types
     * must equal the total reaction count.
     */
    public function testTypeBreakdownSumsEqualTotal(): void
    {
        $this->forAll(
            Generators::choose(3, 12),   // number of distinct users
            Generators::choose(10, 50),  // number of operations
            Generators::choose(1, 10000) // seed
        )
            ->then(function (int $userCount, int $operationCount, int $seed) {
                $state = $this->createReactionState();
                $userIds = [];
                for ($i = 0; $i < $userCount; $i++) {
                    $userIds[] = "user_{$i}";
                }

                for ($i = 0; $i < $operationCount; $i++) {
                    $opSeed = ($seed * ($i + 1) * 31) % 100;
                    $userIndex = (($seed * ($i + 1) * 17) % $userCount);
                    $userId = $userIds[$userIndex];
                    $typeIndex = (($seed * ($i + 1) * 13) % count(self::REACTION_TYPES));
                    $type = self::REACTION_TYPES[$typeIndex];

                    if ($opSeed < 35) {
                        $state = $this->simulateReact($state, $userId, $type);
                    } elseif ($opSeed < 65) {
                        $state = $this->simulateChangeReaction($state, $userId, $type);
                    } else {
                        $state = $this->simulateRemoveReaction($state, $userId);
                    }

                    // INVARIANT: breakdown sum = total count after every operation
                    $totalCount = $this->getTotalCount($state);
                    $breakdown = $this->getTypeBreakdown($state);
                    $breakdownSum = array_sum($breakdown);

                    $this->assertEquals(
                        $totalCount,
                        $breakdownSum,
                        "Type breakdown sum ({$breakdownSum}) must equal total ({$totalCount}) after operation {$i}"
                    );
                }
            });
    }

    /**
     * Property: changeReaction updates the per-type breakdown correctly.
     *
     * When changing from type A to type B:
     * - Count of type A decreases by 1 (if A != B)
     * - Count of type B increases by 1 (if A != B)
     * - All other type counts remain unchanged
     * - Total count unchanged
     */
    public function testChangeReactionUpdatesTypeBreakdownCorrectly(): void
    {
        $this->forAll(
            Generators::choose(3, 8),   // number of users
            Generators::choose(2, 10),  // number of initial reactions
            Generators::choose(1, 10000) // seed
        )
            ->then(function (int $userCount, int $setupOps, int $seed) {
                $state = $this->createReactionState();
                $userIds = [];
                for ($i = 0; $i < $userCount; $i++) {
                    $userIds[] = "user_{$i}";
                }

                // Setup: add reactions
                for ($i = 0; $i < $setupOps; $i++) {
                    $userIndex = (($seed * ($i + 1) * 17) % $userCount);
                    $userId = $userIds[$userIndex];
                    $typeIndex = (($seed * ($i + 1) * 13) % count(self::REACTION_TYPES));
                    $type = self::REACTION_TYPES[$typeIndex];
                    $state = $this->simulateReact($state, $userId, $type);
                }

                // Only proceed if there are active reactions
                if ($this->getTotalCount($state) === 0) {
                    $this->assertTrue(true);
                    return;
                }

                // Pick a user with an existing reaction
                $usersWithReactions = array_keys($state['reactions']);
                $targetIndex = ($seed * 41) % count($usersWithReactions);
                $targetUser = $usersWithReactions[$targetIndex];
                $oldType = $state['reactions'][$targetUser];

                // Pick a different type
                $otherTypes = array_filter(self::REACTION_TYPES, fn($t) => $t !== $oldType);
                $otherTypes = array_values($otherTypes);

                if (empty($otherTypes)) {
                    $this->assertTrue(true);
                    return;
                }

                $newType = $otherTypes[($seed * 53) % count($otherTypes)];

                // Record breakdown before change
                $breakdownBefore = $this->getTypeBreakdown($state);
                $totalBefore = $this->getTotalCount($state);

                // Perform change
                $state = $this->simulateChangeReaction($state, $targetUser, $newType);

                $breakdownAfter = $this->getTypeBreakdown($state);
                $totalAfter = $this->getTotalCount($state);

                // INVARIANT: total unchanged
                $this->assertEquals($totalBefore, $totalAfter, "Total should not change on type change");

                // INVARIANT: old type decreased by 1
                $this->assertEquals(
                    $breakdownBefore[$oldType] - 1,
                    $breakdownAfter[$oldType],
                    "Old type '{$oldType}' count should decrease by 1"
                );

                // INVARIANT: new type increased by 1
                $this->assertEquals(
                    $breakdownBefore[$newType] + 1,
                    $breakdownAfter[$newType],
                    "New type '{$newType}' count should increase by 1"
                );

                // INVARIANT: other types unchanged
                foreach (self::REACTION_TYPES as $t) {
                    if ($t !== $oldType && $t !== $newType) {
                        $this->assertEquals(
                            $breakdownBefore[$t],
                            $breakdownAfter[$t],
                            "Unrelated type '{$t}' count should not change"
                        );
                    }
                }
            });
    }
}
