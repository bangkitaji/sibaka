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
 * Property 11: Anonymous Metadata Purge
 *
 * For any set of anonymous metadata records with varied creation timestamps,
 * purgeExpiredMetadata() SHALL delete exactly those records where created_at
 * is more than 90 days old, and SHALL retain all records ≤ 90 days old.
 *
 * **Validates: Requirements 5.6**
 */
class MetadataPurgePropertyTest extends TestCase
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
     * Property: Purge deletes records older than 90 days.
     *
     * Generate random ages (91-365 days), create records at those ages,
     * run purge, verify all are deleted.
     */
    public function testPurgeDeletesRecordsOlderThanNinetyDays(): void
    {
        $this->forAll(
            Generators::choose(1, 5) // number of records to create
        )
            ->then(function (int $recordCount) {
                // Clean up from previous iterations
                AnonymousMetadata::query()->delete();

                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create();

                $createdIds = [];
                for ($i = 0; $i < $recordCount; $i++) {
                    // Age between 91 and 365 days old (strictly older than 90 days)
                    $daysOld = rand(91, 365);
                    $record = AnonymousMetadata::factory()->create([
                        'author_id' => $user->id,
                        'created_at' => now()->subDays($daysOld),
                    ]);
                    $createdIds[] = $record->id;
                }

                $deleted = $this->anonymityService->purgeExpiredMetadata();

                $this->assertEquals(
                    $recordCount,
                    $deleted,
                    "Purge should delete all {$recordCount} records older than 90 days, but deleted {$deleted}"
                );

                // Verify records no longer exist
                foreach ($createdIds as $id) {
                    $this->assertDatabaseMissing('anonymous_metadata', ['id' => $id]);
                }

                Carbon::setTestNow();
            });
    }

    /**
     * Property: Purge retains records that are 90 days old or less.
     *
     * Generate random ages (0-89 days), create records at those ages,
     * run purge, verify none are deleted.
     */
    public function testPurgeRetainsRecordsNinetyDaysOrNewer(): void
    {
        $this->forAll(
            Generators::choose(1, 5) // number of records to create
        )
            ->then(function (int $recordCount) {
                // Clean up from previous iterations
                AnonymousMetadata::query()->delete();

                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create();

                $createdIds = [];
                for ($i = 0; $i < $recordCount; $i++) {
                    // Age between 0 and 89 days old (within retention window)
                    $daysOld = rand(0, 89);
                    $record = AnonymousMetadata::factory()->create([
                        'author_id' => $user->id,
                        'created_at' => now()->subDays($daysOld),
                    ]);
                    $createdIds[] = $record->id;
                }

                $deleted = $this->anonymityService->purgeExpiredMetadata();

                $this->assertEquals(
                    0,
                    $deleted,
                    "Purge should not delete any records <= 90 days old, but deleted {$deleted}"
                );

                // Verify records still exist
                foreach ($createdIds as $id) {
                    $this->assertDatabaseHas('anonymous_metadata', ['id' => $id]);
                }

                Carbon::setTestNow();
            });
    }

    /**
     * Property: Boundary - record at exactly 90 days old should NOT be deleted.
     *
     * The purge condition is `created_at < now()->subDays(90)`, so a record
     * created exactly 90 days ago has created_at == threshold and is NOT deleted.
     */
    public function testPurgeRetainsRecordAtExactlyNinetyDaysBoundary(): void
    {
        $this->forAll(
            Generators::choose(1, 3) // number of records to create at boundary
        )
            ->then(function (int $recordCount) {
                // Clean up from previous iterations
                AnonymousMetadata::query()->delete();

                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create();

                $createdIds = [];
                for ($i = 0; $i < $recordCount; $i++) {
                    $record = AnonymousMetadata::factory()->create([
                        'author_id' => $user->id,
                        'created_at' => now()->subDays(90), // exactly at the boundary
                    ]);
                    $createdIds[] = $record->id;
                }

                $deleted = $this->anonymityService->purgeExpiredMetadata();

                $this->assertEquals(
                    0,
                    $deleted,
                    "Purge should NOT delete records at exactly 90 days, but deleted {$deleted}"
                );

                // Verify boundary records still exist
                foreach ($createdIds as $id) {
                    $this->assertDatabaseHas('anonymous_metadata', ['id' => $id]);
                }

                Carbon::setTestNow();
            });
    }

    /**
     * Property: For a mixed set of records, purge deletes exactly those > 90 days old.
     *
     * Generate random counts of old (>90 days) and new (<=90 days) records,
     * run purge, verify the return count matches old records and new records remain.
     */
    public function testPurgeDeletesExactlyExpiredRecordsFromMixedSet(): void
    {
        $this->forAll(
            Generators::choose(1, 4), // old records (> 90 days)
            Generators::choose(1, 4)  // new records (<= 90 days)
        )
            ->then(function (int $oldCount, int $newCount) {
                // Clean up from previous iterations
                AnonymousMetadata::query()->delete();

                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create();

                $oldIds = [];
                $newIds = [];

                // Create old records (> 90 days)
                for ($i = 0; $i < $oldCount; $i++) {
                    $daysOld = rand(91, 365);
                    $record = AnonymousMetadata::factory()->create([
                        'author_id' => $user->id,
                        'created_at' => now()->subDays($daysOld),
                    ]);
                    $oldIds[] = $record->id;
                }

                // Create new records (<= 90 days)
                for ($i = 0; $i < $newCount; $i++) {
                    $daysOld = rand(0, 89);
                    $record = AnonymousMetadata::factory()->create([
                        'author_id' => $user->id,
                        'created_at' => now()->subDays($daysOld),
                    ]);
                    $newIds[] = $record->id;
                }

                $deleted = $this->anonymityService->purgeExpiredMetadata();

                $this->assertEquals(
                    $oldCount,
                    $deleted,
                    "Purge should delete exactly {$oldCount} old records, but deleted {$deleted}"
                );

                // Verify old records are gone
                foreach ($oldIds as $id) {
                    $this->assertDatabaseMissing('anonymous_metadata', ['id' => $id]);
                }

                // Verify new records are retained
                foreach ($newIds as $id) {
                    $this->assertDatabaseHas('anonymous_metadata', ['id' => $id]);
                }

                Carbon::setTestNow();
            });
    }

    /**
     * Property: Purge returns the exact count of deleted records.
     *
     * Generate random count of expired records, run purge, verify
     * the returned integer matches the number of expired records.
     */
    public function testPurgeReturnsCorrectDeletedCount(): void
    {
        $this->forAll(
            Generators::choose(0, 6) // number of expired records
        )
            ->then(function (int $expiredCount) {
                // Clean up from previous iterations
                AnonymousMetadata::query()->delete();

                Carbon::setTestNow(Carbon::now());

                $user = User::factory()->member()->create();

                // Create expired records
                for ($i = 0; $i < $expiredCount; $i++) {
                    $daysOld = rand(91, 365);
                    AnonymousMetadata::factory()->create([
                        'author_id' => $user->id,
                        'created_at' => now()->subDays($daysOld),
                    ]);
                }

                // Also create some non-expired records to ensure they don't affect the count
                $nonExpiredCount = rand(0, 3);
                for ($i = 0; $i < $nonExpiredCount; $i++) {
                    AnonymousMetadata::factory()->create([
                        'author_id' => $user->id,
                        'created_at' => now()->subDays(rand(0, 89)),
                    ]);
                }

                $deleted = $this->anonymityService->purgeExpiredMetadata();

                $this->assertEquals(
                    $expiredCount,
                    $deleted,
                    "Purge should return {$expiredCount} as deleted count, but returned {$deleted}"
                );

                Carbon::setTestNow();
            });
    }
}
