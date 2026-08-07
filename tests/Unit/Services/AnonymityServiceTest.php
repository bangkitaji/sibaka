<?php

namespace Tests\Unit\Services;

use App\Enums\ContentStatus;
use App\Enums\UserRole;
use App\Exceptions\AnonymityException;
use App\Models\AnonymousMetadata;
use App\Models\Content;
use App\Models\User;
use App\Services\AnonymityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnonymityServiceTest extends TestCase
{
    use RefreshDatabase;

    private AnonymityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AnonymityService();
    }

    // --- publishAnonymously Tests ---

    public function testPublishAnonymouslyStoresMetadata(): void
    {
        $user = User::factory()->create();
        $content = Content::factory()->create([
            'author_id' => $user->id,
            'is_anonymous' => true,
        ]);

        $metadata = [
            'ip_hash' => hash('sha256', '192.168.1.1'),
            'browser_fingerprint' => 'fp_abc123',
            'user_agent' => 'Mozilla/5.0 Test Browser',
        ];

        $this->service->publishAnonymously($content->id, $user->id, $metadata);

        $this->assertDatabaseHas('anonymous_metadata', [
            'content_id' => $content->id,
            'author_id' => $user->id,
            'ip_hash' => hash('sha256', '192.168.1.1'),
            'browser_fingerprint' => 'fp_abc123',
            'user_agent' => 'Mozilla/5.0 Test Browser',
        ]);
    }

    public function testPublishAnonymouslySetsExpiresAt90Days(): void
    {
        $user = User::factory()->create();
        $content = Content::factory()->create([
            'author_id' => $user->id,
            'is_anonymous' => true,
        ]);

        $metadata = [
            'ip_hash' => hash('sha256', '10.0.0.1'),
            'browser_fingerprint' => 'fp_xyz',
            'user_agent' => 'TestAgent/1.0',
        ];

        $this->service->publishAnonymously($content->id, $user->id, $metadata);

        $record = AnonymousMetadata::where('content_id', $content->id)->first();
        $this->assertNotNull($record);
        $this->assertNotNull($record->expires_at);

        // expires_at should be approximately 90 days from now
        $expectedExpiry = now()->addDays(90);
        $this->assertTrue(
            $record->expires_at->diffInMinutes($expectedExpiry) < 2,
            'expires_at should be 90 days from now'
        );
    }

    public function testPublishAnonymouslyHashesRawIpWhenIpHashNotProvided(): void
    {
        $user = User::factory()->create();
        $content = Content::factory()->create([
            'author_id' => $user->id,
            'is_anonymous' => true,
        ]);

        $metadata = [
            'ip' => '192.168.1.100',
            'browser_fingerprint' => 'fp_test',
            'user_agent' => 'Agent/2.0',
        ];

        $this->service->publishAnonymously($content->id, $user->id, $metadata);

        $record = AnonymousMetadata::where('content_id', $content->id)->first();
        $this->assertEquals(hash('sha256', '192.168.1.100'), $record->ip_hash);
    }

    // --- canPublishAnonymously Tests ---

    public function testCanPublishAnonymouslyReturnsTrueWhenUnderLimit(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->service->canPublishAnonymously($user->id));
    }

    public function testCanPublishAnonymouslyReturnsTrueWithLessThan5PostsIn24Hours(): void
    {
        $user = User::factory()->create();

        // Create 4 anonymous posts within last 24 hours
        for ($i = 0; $i < 4; $i++) {
            $content = Content::factory()->create([
                'author_id' => $user->id,
                'is_anonymous' => true,
            ]);
            AnonymousMetadata::factory()->create([
                'content_id' => $content->id,
                'author_id' => $user->id,
                'created_at' => now()->subHours(rand(1, 23)),
            ]);
        }

        $this->assertTrue($this->service->canPublishAnonymously($user->id));
    }

    public function testCanPublishAnonymouslyReturnsFalseAt5PostsIn24Hours(): void
    {
        $user = User::factory()->create();

        // Create 5 anonymous posts within last 24 hours
        for ($i = 0; $i < 5; $i++) {
            $content = Content::factory()->create([
                'author_id' => $user->id,
                'is_anonymous' => true,
            ]);
            AnonymousMetadata::factory()->create([
                'content_id' => $content->id,
                'author_id' => $user->id,
                'created_at' => now()->subHours(rand(1, 20)),
            ]);
        }

        $this->assertFalse($this->service->canPublishAnonymously($user->id));
    }

    public function testCanPublishAnonymouslyIgnoresPostsOlderThan24Hours(): void
    {
        $user = User::factory()->create();

        // Create 5 anonymous posts older than 24 hours
        for ($i = 0; $i < 5; $i++) {
            $content = Content::factory()->create([
                'author_id' => $user->id,
                'is_anonymous' => true,
            ]);
            AnonymousMetadata::factory()->create([
                'content_id' => $content->id,
                'author_id' => $user->id,
                'created_at' => now()->subHours(25 + $i),
            ]);
        }

        // Should be allowed since all 5 are older than 24h
        $this->assertTrue($this->service->canPublishAnonymously($user->id));
    }

    public function testCanPublishAnonymouslyCountsOnlySpecificAuthor(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create 5 anonymous posts by user2
        for ($i = 0; $i < 5; $i++) {
            $content = Content::factory()->create([
                'author_id' => $user2->id,
                'is_anonymous' => true,
            ]);
            AnonymousMetadata::factory()->create([
                'content_id' => $content->id,
                'author_id' => $user2->id,
                'created_at' => now()->subHours(1),
            ]);
        }

        // user1 should still be able to post
        $this->assertTrue($this->service->canPublishAnonymously($user1->id));
    }

    // --- getAuthorForModeration Tests ---

    public function testGetAuthorForModerationReturnsAuthorIdForModerator(): void
    {
        $author = User::factory()->create();
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);

        $content = Content::factory()->create([
            'author_id' => $author->id,
            'is_anonymous' => true,
        ]);
        AnonymousMetadata::factory()->create([
            'content_id' => $content->id,
            'author_id' => $author->id,
        ]);

        $result = $this->service->getAuthorForModeration($content->id, $moderator->id);
        $this->assertEquals($author->id, $result);
    }

    public function testGetAuthorForModerationReturnsAuthorIdForAdmin(): void
    {
        $author = User::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $content = Content::factory()->create([
            'author_id' => $author->id,
            'is_anonymous' => true,
        ]);
        AnonymousMetadata::factory()->create([
            'content_id' => $content->id,
            'author_id' => $author->id,
        ]);

        $result = $this->service->getAuthorForModeration($content->id, $admin->id);
        $this->assertEquals($author->id, $result);
    }

    public function testGetAuthorForModerationThrowsForRegularMember(): void
    {
        $author = User::factory()->create();
        $member = User::factory()->create(['role' => UserRole::Member]);

        $content = Content::factory()->create([
            'author_id' => $author->id,
            'is_anonymous' => true,
        ]);
        AnonymousMetadata::factory()->create([
            'content_id' => $content->id,
            'author_id' => $author->id,
        ]);

        $this->expectException(AnonymityException::class);
        $this->expectExceptionCode(403);
        $this->service->getAuthorForModeration($content->id, $member->id);
    }

    public function testGetAuthorForModerationThrowsForPendingUser(): void
    {
        $author = User::factory()->create();
        $pending = User::factory()->create(['role' => UserRole::Pending]);

        $content = Content::factory()->create([
            'author_id' => $author->id,
            'is_anonymous' => true,
        ]);
        AnonymousMetadata::factory()->create([
            'content_id' => $content->id,
            'author_id' => $author->id,
        ]);

        $this->expectException(AnonymityException::class);
        $this->expectExceptionCode(403);
        $this->service->getAuthorForModeration($content->id, $pending->id);
    }

    public function testGetAuthorForModerationThrowsWhenMetadataNotFound(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);
        $content = Content::factory()->create(['is_anonymous' => true]);

        $this->expectException(AnonymityException::class);
        $this->expectExceptionCode(404);
        $this->service->getAuthorForModeration($content->id, $moderator->id);
    }

    // --- purgeExpiredMetadata Tests ---

    public function testPurgeExpiredMetadataDeletesOldRecords(): void
    {
        // Create 3 expired records (older than 90 days)
        for ($i = 0; $i < 3; $i++) {
            $content = Content::factory()->create(['is_anonymous' => true]);
            AnonymousMetadata::factory()->create([
                'content_id' => $content->id,
                'created_at' => now()->subDays(91 + $i),
            ]);
        }

        // Create 2 recent records (within 90 days)
        for ($i = 0; $i < 2; $i++) {
            $content = Content::factory()->create(['is_anonymous' => true]);
            AnonymousMetadata::factory()->create([
                'content_id' => $content->id,
                'created_at' => now()->subDays(30 + $i),
            ]);
        }

        $deleted = $this->service->purgeExpiredMetadata();

        $this->assertEquals(3, $deleted);
        $this->assertEquals(2, AnonymousMetadata::count());
    }

    public function testPurgeExpiredMetadataReturnsZeroWhenNoneExpired(): void
    {
        // Create records within 90 days
        for ($i = 0; $i < 3; $i++) {
            $content = Content::factory()->create(['is_anonymous' => true]);
            AnonymousMetadata::factory()->create([
                'content_id' => $content->id,
                'created_at' => now()->subDays(10 + $i),
            ]);
        }

        $deleted = $this->service->purgeExpiredMetadata();

        $this->assertEquals(0, $deleted);
        $this->assertEquals(3, AnonymousMetadata::count());
    }

    public function testPurgeExpiredMetadataDoesNotDeleteExactly90DayOldRecords(): void
    {
        $content = Content::factory()->create(['is_anonymous' => true]);
        AnonymousMetadata::factory()->create([
            'content_id' => $content->id,
            'created_at' => now()->subDays(90),
        ]);

        $deleted = $this->service->purgeExpiredMetadata();

        $this->assertEquals(0, $deleted);
        $this->assertEquals(1, AnonymousMetadata::count());
    }

    // --- Irreversibility (tested via ContentService) ---

    public function testContentServiceRejectsAnonymousToNonAnonymousUpdate(): void
    {
        $user = User::factory()->create();
        $content = Content::factory()->create([
            'author_id' => $user->id,
            'is_anonymous' => true,
            'status' => ContentStatus::Published,
        ]);

        $contentService = new \App\Services\ContentService();

        $this->expectException(AnonymityException::class);
        $this->expectExceptionCode(403);

        $contentService->updateContent($content->id, [
            'is_anonymous' => false,
        ], $user->id);
    }

    public function testContentServiceAllowsOtherUpdatesOnAnonymousContent(): void
    {
        $user = User::factory()->create();
        $content = Content::factory()->create([
            'author_id' => $user->id,
            'is_anonymous' => true,
            'title' => 'Original Title',
            'status' => ContentStatus::Published,
        ]);

        $contentService = new \App\Services\ContentService();

        $updated = $contentService->updateContent($content->id, [
            'title' => 'Updated Title',
        ], $user->id);

        $this->assertEquals('Updated Title', $updated->title);
        $this->assertTrue($updated->is_anonymous);
    }
}
