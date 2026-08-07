<?php

use App\Enums\ContentCategory;
use App\Enums\ContentStatus;
use App\Enums\ReactionType;
use App\Enums\TagCategory;
use App\Models\Comment;
use App\Models\Content;
use App\Models\Tag;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Content Creation → Publish → View → React → Comment Flow
|--------------------------------------------------------------------------
|
| Tests the complete content lifecycle: a verified member creates content,
| publishes it, another member views it, reacts to it, and leaves a comment.
|
| Validates: Requirements 3.1, 3.7, 4.1, 6.2, 7.1, 7.7, 8.1, 8.2
|
*/

describe('Content Creation → Publish → View → React → Comment Flow', function () {

    beforeEach(function () {
        // Seed the predefined tags required for content creation
        $this->seed(\Database\Seeders\TagSeeder::class);
    });

    test('member creates content with tags, publishes, another member views, reacts, and comments', function () {
        $author = User::factory()->member()->create();
        $viewer = User::factory()->member()->create();

        // Step 1: Create content (as draft)
        $contentData = [
            'title' => 'How We Migrated to Kubernetes',
            'body' => 'This is a detailed post-mortem about our migration journey to Kubernetes. ' . str_repeat('Content body. ', 50),
            'category' => 'post_mortem',
            'is_anonymous' => false,
            'is_qna' => false,
            'publish' => true,
            'tags' => [
                'tech_stack' => ['kubernetes', 'docker'],
                'experience_level' => 'advanced',
                'category' => 'incident',
            ],
        ];

        $createResponse = $this->actingAs($author)
            ->post('/content', $contentData);

        $createResponse->assertRedirect();

        // Verify content was created and published
        $content = Content::where('author_id', $author->id)->first();
        expect($content)->not->toBeNull();
        expect($content->title)->toBe('How We Migrated to Kubernetes');
        expect($content->category)->toBe(ContentCategory::PostMortem);
        expect($content->status)->toBe(ContentStatus::Published);
        expect($content->published_at)->not->toBeNull();
        expect($content->is_anonymous)->toBeFalse();

        // Verify tags were attached
        $contentTags = $content->tags()->pluck('name')->toArray();
        expect($contentTags)->toContain('kubernetes');
        expect($contentTags)->toContain('docker');
        expect($contentTags)->toContain('advanced');
        expect($contentTags)->toContain('incident');

        // Step 2: Another member views the content
        $viewResponse = $this->actingAs($viewer)
            ->get("/content/{$content->id}");

        $viewResponse->assertOk();

        // Step 3: Viewer reacts to the content
        $reactResponse = $this->actingAs($viewer)
            ->postJson("/content/{$content->id}/reactions", [
                'type' => 'insightful',
            ]);

        $reactResponse->assertOk();
        $reactResponse->assertJsonFragment(['message' => 'Reaction saved.']);

        // Verify reaction was stored
        $this->assertDatabaseHas('reactions', [
            'content_id' => $content->id,
            'user_id' => $viewer->id,
            'type' => 'insightful',
        ]);

        // Step 4: Viewer adds a comment
        $commentResponse = $this->actingAs($viewer)
            ->postJson("/content/{$content->id}/comments", [
                'text' => 'Great post-mortem! We had a similar experience with our k8s migration.',
                'is_anonymous' => false,
            ]);

        $commentResponse->assertCreated();
        $commentResponse->assertJsonFragment(['message' => 'Comment added successfully.']);

        // Verify comment was stored
        $comment = Comment::where('content_id', $content->id)
            ->where('author_id', $viewer->id)
            ->first();
        expect($comment)->not->toBeNull();
        expect($comment->body)->toBe('Great post-mortem! We had a similar experience with our k8s migration.');
        expect($comment->depth)->toBe(0);
    });

    test('content creation fails without required tags', function () {
        $author = User::factory()->member()->create();

        $contentData = [
            'title' => 'Incomplete Content',
            'body' => 'This content has no tags and should fail validation.',
            'category' => 'tech_stack',
            'is_anonymous' => false,
            'is_qna' => false,
            'publish' => true,
            'tags' => [
                'tech_stack' => [], // Missing required tech_stack tags
                'experience_level' => 'beginner',
                'category' => 'architecture',
            ],
        ];

        $response = $this->actingAs($author)
            ->post('/content', $contentData);

        // Should fail validation
        $response->assertSessionHasErrors('tags.tech_stack');
    });

    test('member can change reaction type on content', function () {
        $author = User::factory()->member()->create();
        $viewer = User::factory()->member()->create();

        $content = Content::factory()->published()->create([
            'author_id' => $author->id,
        ]);

        // React with 'helpful' first
        $this->actingAs($viewer)
            ->postJson("/content/{$content->id}/reactions", ['type' => 'helpful']);

        $this->assertDatabaseHas('reactions', [
            'content_id' => $content->id,
            'user_id' => $viewer->id,
            'type' => 'helpful',
        ]);

        // Change reaction to 'solutif'
        $this->actingAs($viewer)
            ->postJson("/content/{$content->id}/reactions", ['type' => 'solutif']);

        // Should only have one reaction (the new one)
        $this->assertDatabaseHas('reactions', [
            'content_id' => $content->id,
            'user_id' => $viewer->id,
            'type' => 'solutif',
        ]);

        $this->assertDatabaseMissing('reactions', [
            'content_id' => $content->id,
            'user_id' => $viewer->id,
            'type' => 'helpful',
        ]);
    });

    test('threaded comment replies maintain depth correctly', function () {
        $author = User::factory()->member()->create();
        $commenter1 = User::factory()->member()->create();
        $commenter2 = User::factory()->member()->create();

        $content = Content::factory()->published()->create([
            'author_id' => $author->id,
        ]);

        // First top-level comment
        $response1 = $this->actingAs($commenter1)
            ->postJson("/content/{$content->id}/comments", [
                'text' => 'Top level comment',
                'is_anonymous' => false,
            ]);

        $response1->assertCreated();
        $parentComment = Comment::where('content_id', $content->id)
            ->where('author_id', $commenter1->id)
            ->first();
        expect($parentComment->depth)->toBe(0);

        // Reply to top-level comment (depth 1)
        $response2 = $this->actingAs($commenter2)
            ->postJson("/content/{$content->id}/comments", [
                'text' => 'Reply to top level comment',
                'parent_id' => $parentComment->id,
                'is_anonymous' => false,
            ]);

        $response2->assertCreated();
        $replyComment = Comment::where('content_id', $content->id)
            ->where('author_id', $commenter2->id)
            ->first();
        expect($replyComment->depth)->toBe(1);
        expect($replyComment->parent_id)->toBe($parentComment->id);
    });

    test('comments are rejected on locked threads', function () {
        $author = User::factory()->member()->create();
        $commenter = User::factory()->member()->create();

        $content = Content::factory()->published()->locked()->create([
            'author_id' => $author->id,
        ]);

        $response = $this->actingAs($commenter)
            ->postJson("/content/{$content->id}/comments", [
                'text' => 'Trying to comment on a locked thread.',
                'is_anonymous' => false,
            ]);

        $response->assertStatus(403);
    });

    test('unauthenticated user cannot create content', function () {
        $response = $this->post('/content', [
            'title' => 'Unauthorized Content',
            'body' => 'This should fail.',
            'category' => 'tech_stack',
        ]);

        $response->assertRedirect('/login');
    });

    test('pending user cannot create content', function () {
        $pendingUser = User::factory()->pending()->create();

        $response = $this->actingAs($pendingUser)
            ->post('/content', [
                'title' => 'Unauthorized Content',
                'body' => 'Pending users cannot post.',
                'category' => 'tech_stack',
            ]);

        $response->assertRedirect(route('verification.pending'));
    });
});
