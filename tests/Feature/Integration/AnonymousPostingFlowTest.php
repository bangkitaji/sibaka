<?php

use App\Enums\ContentCategory;
use App\Enums\ContentStatus;
use App\Models\AnonymousMetadata;
use App\Models\Content;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Anonymous Posting → Moderation Identification Flow
|--------------------------------------------------------------------------
|
| Tests the anonymous content lifecycle: a member publishes anonymously,
| the content appears without identity information publicly, and a moderator
| can identify the author through internal metadata.
|
| Validates: Requirements 5.1, 5.2, 5.3, 5.4, 5.5, 5.7, 5.9
|
*/

describe('Anonymous Posting → Moderation Identification Flow', function () {

    beforeEach(function () {
        $this->seed(\Database\Seeders\TagSeeder::class);
    });

    test('member publishes content anonymously and identity is stripped from public view', function () {
        $author = User::factory()->member()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@alumni.test',
        ]);
        $viewer = User::factory()->member()->create();

        // Step 1: Create anonymous content
        $contentData = [
            'title' => 'Toxic Workplace Culture at My Company',
            'body' => 'I want to share my experience without revealing who I am. ' . str_repeat('Anonymous content. ', 30),
            'category' => 'career_interview',
            'is_anonymous' => true,
            'is_qna' => false,
            'publish' => true,
            'tags' => [
                'tech_stack' => ['python'],
                'experience_level' => 'intermediate',
                'category' => 'career',
            ],
        ];

        $response = $this->actingAs($author)
            ->post('/content', $contentData);

        $response->assertRedirect();

        // Step 2: Verify the content was created with anonymous flag
        $content = Content::where('title', 'Toxic Workplace Culture at My Company')->first();
        expect($content)->not->toBeNull();
        expect($content->is_anonymous)->toBeTrue();
        expect($content->author_id)->toBe($author->id);
        expect($content->status)->toBe(ContentStatus::Published);

        // Step 3: Store anonymous metadata (as would happen in the full pipeline)
        $anonymityService = app(\App\Contracts\AnonymityServiceInterface::class);
        $anonymityService->publishAnonymously($content->id, $author->id, [
            'ip' => '127.0.0.1',
            'browser_fingerprint' => 'test-fingerprint',
            'user_agent' => 'Mozilla/5.0 Test',
        ]);

        $metadata = AnonymousMetadata::where('content_id', $content->id)->first();
        expect($metadata)->not->toBeNull();
        expect($metadata->author_id)->toBe($author->id);
        expect($metadata->expires_at)->not->toBeNull();

        // Step 4: Public view should NOT reveal author identity
        $viewResponse = $this->actingAs($viewer)
            ->get("/content/{$content->id}");

        $viewResponse->assertOk();

        // The Inertia response should show "Anonymous Member" instead of actual author
        $viewResponse->assertDontSee('Budi Santoso');
        $viewResponse->assertDontSee('budi@alumni.test');
    });

    test('moderator can identify anonymous content author via internal metadata', function () {
        $author = User::factory()->member()->create([
            'name' => 'Secret Author',
        ]);
        $moderator = User::factory()->moderator()->create();

        // Create anonymous content with metadata
        $content = Content::factory()->published()->anonymous()->create([
            'author_id' => $author->id,
        ]);

        AnonymousMetadata::create([
            'content_id' => $content->id,
            'author_id' => $author->id,
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'browser_fingerprint' => 'test-fingerprint-abc123',
            'user_agent' => 'Mozilla/5.0 Test',
            'expires_at' => now()->addDays(90),
        ]);

        // Verify the metadata exists and is accessible internally
        $metadata = AnonymousMetadata::where('content_id', $content->id)->first();
        expect($metadata)->not->toBeNull();
        expect($metadata->author_id)->toBe($author->id);

        // The moderation service can identify the author
        $anonymityService = app(\App\Contracts\AnonymityServiceInterface::class);
        $identifiedAuthor = $anonymityService->getAuthorForModeration($content->id, $moderator->id);
        expect($identifiedAuthor)->toBe($author->id);
    });

    test('non-moderator cannot identify anonymous content author', function () {
        $author = User::factory()->member()->create();
        $regularMember = User::factory()->member()->create();

        $content = Content::factory()->published()->anonymous()->create([
            'author_id' => $author->id,
        ]);

        AnonymousMetadata::create([
            'content_id' => $content->id,
            'author_id' => $author->id,
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'browser_fingerprint' => 'fingerprint',
            'user_agent' => 'Mozilla/5.0',
            'expires_at' => now()->addDays(90),
        ]);

        // Regular member should NOT be able to identify author
        $anonymityService = app(\App\Contracts\AnonymityServiceInterface::class);

        expect(fn () => $anonymityService->getAuthorForModeration($content->id, $regularMember->id))
            ->toThrow(\App\Exceptions\AnonymityException::class);
    });

    test('anonymous content cannot be converted to non-anonymous', function () {
        $author = User::factory()->member()->create();

        $content = Content::factory()->published()->anonymous()->create([
            'author_id' => $author->id,
            'title' => 'Cannot Reveal',
        ]);

        // Attempt to update is_anonymous to false
        $response = $this->actingAs($author)
            ->put("/content/{$content->id}", [
                'title' => 'Cannot Reveal',
                'body' => $content->body,
                'category' => $content->category->value,
                'is_anonymous' => false, // Should be rejected
            ]);

        // Should get an error (either 403 or redirect with error)
        $content->refresh();
        expect($content->is_anonymous)->toBeTrue();
    });

    test('anonymous posting rate limit blocks after 5 posts in 24 hours', function () {
        $author = User::factory()->member()->create();

        // Create 5 anonymous metadata records within 24 hours (simulating 5 anonymous posts)
        for ($i = 0; $i < 5; $i++) {
            $content = Content::factory()->published()->anonymous()->create([
                'author_id' => $author->id,
            ]);

            AnonymousMetadata::create([
                'content_id' => $content->id,
                'author_id' => $author->id,
                'ip_hash' => hash('sha256', '127.0.0.1'),
                'browser_fingerprint' => 'fingerprint',
                'user_agent' => 'Mozilla/5.0',
                'expires_at' => now()->addDays(90),
            ]);
        }

        // Verify the rate limit is enforced
        $anonymityService = app(\App\Contracts\AnonymityServiceInterface::class);
        expect($anonymityService->canPublishAnonymously($author->id))->toBeFalse();
    });

    test('anonymous metadata is purged after 90 days', function () {
        $author = User::factory()->member()->create();

        // Create metadata older than 90 days
        $oldContent = Content::factory()->published()->anonymous()->create([
            'author_id' => $author->id,
        ]);

        $oldMetadata = AnonymousMetadata::create([
            'content_id' => $oldContent->id,
            'author_id' => $author->id,
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'browser_fingerprint' => 'old-fingerprint',
            'user_agent' => 'Mozilla/5.0',
            'expires_at' => now()->subDay(), // Already expired
            'created_at' => now()->subDays(91),
        ]);

        // Create metadata within 90 days
        $newContent = Content::factory()->published()->anonymous()->create([
            'author_id' => $author->id,
        ]);

        $newMetadata = AnonymousMetadata::create([
            'content_id' => $newContent->id,
            'author_id' => $author->id,
            'ip_hash' => hash('sha256', '127.0.0.2'),
            'browser_fingerprint' => 'new-fingerprint',
            'user_agent' => 'Mozilla/5.0',
            'expires_at' => now()->addDays(30), // Not yet expired
            'created_at' => now()->subDays(60),
        ]);

        // Run the purge
        $anonymityService = app(\App\Contracts\AnonymityServiceInterface::class);
        $purgedCount = $anonymityService->purgeExpiredMetadata();

        // Old metadata should be purged, new should remain
        expect(AnonymousMetadata::find($oldMetadata->id))->toBeNull();
        expect(AnonymousMetadata::find($newMetadata->id))->not->toBeNull();
    });
});
