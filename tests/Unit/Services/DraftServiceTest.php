<?php

namespace Tests\Unit\Services;

use App\Models\Content;
use App\Models\Draft;
use App\Models\User;
use App\Services\DraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class DraftServiceTest extends TestCase
{
    use RefreshDatabase;

    private DraftService $draftService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->draftService = new DraftService();
    }

    public function testSaveStoresDraftInRedis(): void
    {
        $contentId = 'test-content-id';
        $authorId = 'test-author-id';
        $body = '<p>Auto-saved content</p>';

        Redis::shouldReceive('setex')
            ->once()
            ->with('draft:' . $contentId, 604800, $body);

        $this->draftService->save($contentId, $authorId, $body);
    }

    public function testSaveFallsBackToDBWhenRedisUnavailable(): void
    {
        $user = User::factory()->create();
        $content = Content::factory()->create(['author_id' => $user->id]);

        Redis::shouldReceive('setex')
            ->once()
            ->andThrow(new \Exception('Redis connection refused'));

        $this->draftService->save($content->id, $user->id, '<p>Fallback content</p>');

        $this->assertDatabaseHas('drafts', [
            'content_id' => $content->id,
            'author_id' => $user->id,
            'body' => '<p>Fallback content</p>',
        ]);
    }

    public function testRestoreReturnsDraftFromRedis(): void
    {
        $contentId = 'test-content-id';
        $expectedBody = '<p>Draft body from Redis</p>';

        Redis::shouldReceive('get')
            ->once()
            ->with('draft:' . $contentId)
            ->andReturn($expectedBody);

        $result = $this->draftService->restore($contentId);
        $this->assertEquals($expectedBody, $result);
    }

    public function testRestoreReturnsNullWhenNoDraftExists(): void
    {
        $contentId = '00000000-0000-0000-0000-000000000000';

        Redis::shouldReceive('get')
            ->once()
            ->with('draft:' . $contentId)
            ->andReturn(null);

        $result = $this->draftService->restore($contentId);
        $this->assertNull($result);
    }

    public function testRestoreFallsBackToDBWhenRedisUnavailable(): void
    {
        $user = User::factory()->create();
        $content = Content::factory()->create(['author_id' => $user->id]);

        // Create a draft in the database
        Draft::create([
            'content_id' => $content->id,
            'author_id' => $user->id,
            'body' => '<p>DB draft content</p>',
            'saved_at' => now(),
        ]);

        Redis::shouldReceive('get')
            ->once()
            ->andThrow(new \Exception('Redis connection refused'));

        $result = $this->draftService->restore($content->id);
        $this->assertEquals('<p>DB draft content</p>', $result);
    }

    public function testDeleteRemovesDraftFromRedisAndDB(): void
    {
        $user = User::factory()->create();
        $content = Content::factory()->create(['author_id' => $user->id]);

        // Create a draft in the database
        Draft::create([
            'content_id' => $content->id,
            'author_id' => $user->id,
            'body' => '<p>To be deleted</p>',
            'saved_at' => now(),
        ]);

        Redis::shouldReceive('del')
            ->once()
            ->with('draft:' . $content->id);

        $this->draftService->delete($content->id);

        $this->assertDatabaseMissing('drafts', [
            'content_id' => $content->id,
        ]);
    }

    public function testDeleteHandlesRedisFailureGracefully(): void
    {
        $user = User::factory()->create();
        $content = Content::factory()->create(['author_id' => $user->id]);

        Draft::create([
            'content_id' => $content->id,
            'author_id' => $user->id,
            'body' => '<p>Draft to delete</p>',
            'saved_at' => now(),
        ]);

        Redis::shouldReceive('del')
            ->once()
            ->andThrow(new \Exception('Redis connection refused'));

        // Should still delete from DB even if Redis fails
        $this->draftService->delete($content->id);

        $this->assertDatabaseMissing('drafts', [
            'content_id' => $content->id,
        ]);
    }

    public function testSaveWithTTLOf7Days(): void
    {
        $contentId = 'test-content';
        $authorId = 'test-author';
        $body = 'content';

        // 7 days = 604800 seconds
        Redis::shouldReceive('setex')
            ->once()
            ->with('draft:' . $contentId, 604800, $body);

        $this->draftService->save($contentId, $authorId, $body);
    }
}
