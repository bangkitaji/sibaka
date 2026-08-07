<?php

namespace Tests\Unit\Jobs;

use App\Jobs\PurgeAnonymousMetadata;
use App\Models\AnonymousMetadata;
use App\Models\Content;
use App\Services\AnonymityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PurgeAnonymousMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function testJobPurgesExpiredMetadataAndLogs(): void
    {
        // Create 2 expired records (older than 90 days)
        for ($i = 0; $i < 2; $i++) {
            $content = Content::factory()->create(['is_anonymous' => true]);
            AnonymousMetadata::factory()->create([
                'content_id' => $content->id,
                'created_at' => now()->subDays(91 + $i),
            ]);
        }

        // Create 1 recent record (within 90 days)
        $recentContent = Content::factory()->create(['is_anonymous' => true]);
        AnonymousMetadata::factory()->create([
            'content_id' => $recentContent->id,
            'created_at' => now()->subDays(10),
        ]);

        Log::shouldReceive('info')
            ->once()
            ->with('PurgeAnonymousMetadata: Purged expired anonymous metadata records.', [
                'deleted_count' => 2,
            ]);

        $job = new PurgeAnonymousMetadata();
        $job->handle(new AnonymityService());

        $this->assertEquals(1, AnonymousMetadata::count());
    }

    public function testJobLogsZeroWhenNothingToPurge(): void
    {
        // Create only recent records
        $content = Content::factory()->create(['is_anonymous' => true]);
        AnonymousMetadata::factory()->create([
            'content_id' => $content->id,
            'created_at' => now()->subDays(5),
        ]);

        Log::shouldReceive('info')
            ->once()
            ->with('PurgeAnonymousMetadata: Purged expired anonymous metadata records.', [
                'deleted_count' => 0,
            ]);

        $job = new PurgeAnonymousMetadata();
        $job->handle(new AnonymityService());

        $this->assertEquals(1, AnonymousMetadata::count());
    }
}
