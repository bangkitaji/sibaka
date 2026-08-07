<?php

namespace Tests\Property;

use App\Enums\ReactionType;
use App\Models\Content;
use App\Models\Reaction;
use App\Models\User;
use App\Services\ReactionService;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property 20: Reaction Threshold Badges
 *
 * For any content with random reaction counts per type:
 * - show_breakdown = true iff total reactions >= 50
 * - is_solutif_recommendation = true iff Solutif count >= 10
 * - These thresholds are independent of each other
 *
 * **Validates: Requirements 8.4, 8.5**
 */
class ReactionThresholdPropertyTest extends TestCase
{
    use TestTrait;
    use RefreshDatabase;

    private ReactionService $reactionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reactionService = new ReactionService();
    }

    /**
     * Property: show_breakdown is true iff total reactions >= 50.
     *
     * Generate random reaction counts for each type (0-30 each, allowing totals 0-120),
     * create that many reactions on a content, then verify threshold logic.
     */
    public function testShowBreakdownTrueIffTotalAtLeastFifty(): void
    {
        $this->forAll(
            Generators::choose(0, 30), // insightful count
            Generators::choose(0, 30), // relatable count
            Generators::choose(0, 30), // helpful count
            Generators::choose(0, 30)  // solutif count
        )
            ->then(function (int $insightful, int $relatable, int $helpful, int $solutif) {
                $content = Content::factory()->published()->create();
                $total = $insightful + $relatable + $helpful + $solutif;

                $this->createReactionsForContent($content, $insightful, $relatable, $helpful, $solutif);

                $summary = $this->reactionService->getReactionSummary($content->id);

                $expectedShowBreakdown = $total >= 50;

                $this->assertEquals(
                    $expectedShowBreakdown,
                    $summary['show_breakdown'],
                    "show_breakdown should be " . ($expectedShowBreakdown ? 'true' : 'false')
                    . " when total is {$total} (threshold: 50)"
                );
            });
    }

    /**
     * Property: is_solutif_recommendation is true iff Solutif count >= 10.
     *
     * Generate random solutif counts (0-25) plus other reaction counts,
     * verify the Solutif badge threshold independently.
     */
    public function testSolutifRecommendationTrueIffSolutifAtLeastTen(): void
    {
        $this->forAll(
            Generators::choose(0, 20), // insightful count
            Generators::choose(0, 20), // relatable count
            Generators::choose(0, 20), // helpful count
            Generators::choose(0, 25)  // solutif count (wider range to test threshold)
        )
            ->then(function (int $insightful, int $relatable, int $helpful, int $solutif) {
                $content = Content::factory()->published()->create();

                $this->createReactionsForContent($content, $insightful, $relatable, $helpful, $solutif);

                $summary = $this->reactionService->getReactionSummary($content->id);

                $expectedSolutifBadge = $solutif >= 10;

                $this->assertEquals(
                    $expectedSolutifBadge,
                    $summary['is_solutif_recommendation'],
                    "is_solutif_recommendation should be " . ($expectedSolutifBadge ? 'true' : 'false')
                    . " when solutif count is {$solutif} (threshold: 10)"
                );
            });
    }

    /**
     * Property: Thresholds are independent - can have breakdown without solutif badge and vice versa.
     *
     * Test specific combinations:
     * - High total (>=50) with low solutif (<10): show_breakdown=true, is_solutif_recommendation=false
     * - Low total (<50) with high solutif (>=10): show_breakdown=false, is_solutif_recommendation=true
     */
    public function testThresholdsAreIndependent(): void
    {
        $this->forAll(
            Generators::choose(0, 1) // scenario selector
        )
            ->then(function (int $scenario) {
                $content = Content::factory()->published()->create();

                if ($scenario === 0) {
                    // High total (>=50) with low solutif (<10)
                    // e.g. 20 insightful + 20 relatable + 15 helpful + 5 solutif = 60 total
                    $insightful = rand(15, 25);
                    $relatable = rand(15, 25);
                    $helpful = rand(10, 20);
                    $solutif = rand(0, 9);

                    // Ensure total >= 50
                    $total = $insightful + $relatable + $helpful + $solutif;
                    if ($total < 50) {
                        $insightful += (50 - $total);
                    }

                    $this->createReactionsForContent($content, $insightful, $relatable, $helpful, $solutif);

                    $summary = $this->reactionService->getReactionSummary($content->id);

                    $this->assertTrue(
                        $summary['show_breakdown'],
                        "show_breakdown should be true when total >= 50"
                    );
                    $this->assertFalse(
                        $summary['is_solutif_recommendation'],
                        "is_solutif_recommendation should be false when solutif < 10 (solutif={$solutif})"
                    );
                } else {
                    // Low total (<50) with high solutif (>=10)
                    // e.g. 5 insightful + 5 relatable + 5 helpful + 15 solutif = 30 total
                    $insightful = rand(0, 10);
                    $relatable = rand(0, 10);
                    $helpful = rand(0, 10);
                    $solutif = rand(10, 20);

                    // Ensure total < 50
                    $total = $insightful + $relatable + $helpful + $solutif;
                    if ($total >= 50) {
                        $insightful = 2;
                        $relatable = 2;
                        $helpful = 2;
                        // solutif stays >= 10, total will be solutif + 6 which is at most 26
                        $solutif = rand(10, 15);
                    }

                    $this->createReactionsForContent($content, $insightful, $relatable, $helpful, $solutif);

                    $summary = $this->reactionService->getReactionSummary($content->id);

                    $this->assertFalse(
                        $summary['show_breakdown'],
                        "show_breakdown should be false when total < 50 (total=" . ($insightful + $relatable + $helpful + $solutif) . ")"
                    );
                    $this->assertTrue(
                        $summary['is_solutif_recommendation'],
                        "is_solutif_recommendation should be true when solutif >= 10 (solutif={$solutif})"
                    );
                }
            });
    }

    /**
     * Property: Reaction counts in summary match actual created reactions.
     *
     * Generate random counts, verify the summary totals are accurate.
     */
    public function testReactionCountsInSummaryAreAccurate(): void
    {
        $this->forAll(
            Generators::choose(0, 15), // insightful count
            Generators::choose(0, 15), // relatable count
            Generators::choose(0, 15), // helpful count
            Generators::choose(0, 15)  // solutif count
        )
            ->then(function (int $insightful, int $relatable, int $helpful, int $solutif) {
                $content = Content::factory()->published()->create();
                $total = $insightful + $relatable + $helpful + $solutif;

                $this->createReactionsForContent($content, $insightful, $relatable, $helpful, $solutif);

                $summary = $this->reactionService->getReactionSummary($content->id);

                $this->assertEquals($total, $summary['total'], "Total should be {$total}");
                $this->assertEquals($insightful, $summary['insightful'], "Insightful count should be {$insightful}");
                $this->assertEquals($relatable, $summary['relatable'], "Relatable count should be {$relatable}");
                $this->assertEquals($helpful, $summary['helpful'], "Helpful count should be {$helpful}");
                $this->assertEquals($solutif, $summary['solutif'], "Solutif count should be {$solutif}");
            });
    }

    /**
     * Property: Boundary test - show_breakdown transitions at exactly 50.
     *
     * Generate totals around the boundary (48-52) and verify exact threshold behavior.
     */
    public function testShowBreakdownBoundaryAtFifty(): void
    {
        $this->forAll(
            Generators::choose(48, 52) // total reactions near boundary
        )
            ->then(function (int $targetTotal) {
                $content = Content::factory()->published()->create();

                // Distribute the target total across types
                $solutif = rand(0, min(5, $targetTotal));
                $remaining = $targetTotal - $solutif;
                $insightful = rand(0, $remaining);
                $remaining -= $insightful;
                $relatable = rand(0, $remaining);
                $helpful = $remaining - $relatable;

                $this->createReactionsForContent($content, $insightful, $relatable, $helpful, $solutif);

                $summary = $this->reactionService->getReactionSummary($content->id);

                $expectedShowBreakdown = $targetTotal >= 50;

                $this->assertEquals(
                    $expectedShowBreakdown,
                    $summary['show_breakdown'],
                    "show_breakdown should be " . ($expectedShowBreakdown ? 'true' : 'false')
                    . " at exactly {$targetTotal} total reactions (threshold: 50)"
                );
            });
    }

    /**
     * Property: Boundary test - is_solutif_recommendation transitions at exactly 10 solutif.
     *
     * Generate solutif counts around the boundary (8-12) and verify exact threshold behavior.
     */
    public function testSolutifRecommendationBoundaryAtTen(): void
    {
        $this->forAll(
            Generators::choose(8, 12) // solutif count near boundary
        )
            ->then(function (int $solutifCount) {
                $content = Content::factory()->published()->create();

                // Add some non-solutif reactions too
                $otherCount = rand(0, 10);
                $this->createReactionsForContent($content, $otherCount, 0, 0, $solutifCount);

                $summary = $this->reactionService->getReactionSummary($content->id);

                $expectedSolutifBadge = $solutifCount >= 10;

                $this->assertEquals(
                    $expectedSolutifBadge,
                    $summary['is_solutif_recommendation'],
                    "is_solutif_recommendation should be " . ($expectedSolutifBadge ? 'true' : 'false')
                    . " at exactly {$solutifCount} solutif reactions (threshold: 10)"
                );
            });
    }

    /**
     * Helper: Create reactions of specific types for a content.
     * Each reaction is by a unique user (enforcing one-reaction-per-user constraint).
     */
    private function createReactionsForContent(
        Content $content,
        int $insightfulCount,
        int $relatableCount,
        int $helpfulCount,
        int $solutifCount
    ): void {
        $totalUsers = $insightfulCount + $relatableCount + $helpfulCount + $solutifCount;

        if ($totalUsers === 0) {
            return;
        }

        // Create all users at once for efficiency
        $users = User::factory()->count($totalUsers)->member()->create();
        $userIndex = 0;

        // Create insightful reactions
        for ($i = 0; $i < $insightfulCount; $i++) {
            Reaction::factory()->insightful()->create([
                'content_id' => $content->id,
                'user_id' => $users[$userIndex++]->id,
            ]);
        }

        // Create relatable reactions
        for ($i = 0; $i < $relatableCount; $i++) {
            Reaction::factory()->relatable()->create([
                'content_id' => $content->id,
                'user_id' => $users[$userIndex++]->id,
            ]);
        }

        // Create helpful reactions
        for ($i = 0; $i < $helpfulCount; $i++) {
            Reaction::factory()->helpful()->create([
                'content_id' => $content->id,
                'user_id' => $users[$userIndex++]->id,
            ]);
        }

        // Create solutif reactions
        for ($i = 0; $i < $solutifCount; $i++) {
            Reaction::factory()->solutif()->create([
                'content_id' => $content->id,
                'user_id' => $users[$userIndex++]->id,
            ]);
        }
    }
}
