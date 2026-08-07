<?php

namespace Tests\Unit\Services;

use App\Exceptions\ContentException;
use App\Models\Comment;
use App\Models\Content;
use App\Models\User;
use App\Services\CommentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentServiceTest extends TestCase
{
    use RefreshDatabase;

    private CommentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CommentService();
    }

    // --- addComment Tests ---

    public function testAddCommentCreatesTopLevelComment(): void
    {
        $user = User::factory()->create();
        $content = Content::factory()->create(['is_locked' => false]);

        $comment = $this->service->addComment($content->id, $user->id, [
            'text' => 'This is a test comment',
            'is_anonymous' => false,
        ]);

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'content_id' => $content->id,
            'author_id' => $user->id,
            'body' => 'This is a test comment',
            'depth' => 0,
            'parent_id' => null,
            'is_anonymous' => false,
        ]);
    }

    public function testAddCommentTrimsWhitespace(): void
    {
        $user = User::factory()->create();
        $content = Content::factory()->create(['is_locked' => false]);

        $comment = $this->service->addComment($content->id, $user->id, [
            'text' => '   trimmed text   ',
            'is_anonymous' => false,
        ]);

        $this->assertEquals('trimmed text', $comment->body);
    }

    public function testAddCommentWithParentSetsCorrectDepth(): void
    {
        $user = User::factory()->create();
        $content = Content::factory()->create(['is_locked' => false]);

        $parent = Comment::factory()->create([
            'content_id' => $content->id,
            'depth' => 0,
        ]);

        $reply = $this->service->addComment($content->id, $user->id, [
            'text' => 'This is a reply',
            'parent_id' => $parent->id,
            'is_anonymous' => false,
        ]);

        $this->assertEquals(1, $reply->depth);
        $this->assertEquals($parent->id, $reply->parent_id);
    }

    public function testAddCommentCapsDepthAt5(): void
    {
        $user = User::factory()->create();
        $content = Content::factory()->create(['is_locked' => false]);

        // Create a comment at depth 5
        $deepComment = Comment::factory()->create([
            'content_id' => $content->id,
            'depth' => 5,
        ]);

        // Reply to depth-5 comment should stay at depth 5
        $reply = $this->service->addComment($content->id, $user->id, [
            'text' => 'Reply at max depth',
            'parent_id' => $deepComment->id,
            'is_anonymous' => false,
        ]);

        $this->assertEquals(5, $reply->depth);
    }

    public function testAddCommentIncreasesDepthFromParent(): void
    {
        $user = User::factory()->create();
        $content = Content::factory()->create(['is_locked' => false]);

        // Build a chain: depth 0 -> 1 -> 2 -> 3 -> 4 -> 5
        $current = Comment::factory()->create([
            'content_id' => $content->id,
            'depth' => 0,
        ]);

        for ($expectedDepth = 1; $expectedDepth <= 5; $expectedDepth++) {
            $reply = $this->service->addComment($content->id, $user->id, [
                'text' => "Reply at depth $expectedDepth",
                'parent_id' => $current->id,
                'is_anonymous' => false,
            ]);

            $this->assertEquals($expectedDepth, $reply->depth);
            $current = $reply;
        }

        // One more reply should still be at depth 5
        $flatReply = $this->service->addComment($content->id, $user->id, [
            'text' => 'Reply stays flat at max depth',
            'parent_id' => $current->id,
            'is_anonymous' => false,
        ]);

        $this->assertEquals(5, $flatReply->depth);
    }

    public function testAddCommentRejectsLockedThread(): void
    {
        $user = User::factory()->create();
        $content = Content::factory()->create(['is_locked' => true]);

        $this->expectException(ContentException::class);
        $this->expectExceptionMessage('Thread locked due to inactivity');
        $this->expectExceptionCode(403);

        $this->service->addComment($content->id, $user->id, [
            'text' => 'Should not work',
            'is_anonymous' => false,
        ]);
    }

    public function testAddCommentSupportsAnonymousToggle(): void
    {
        $user = User::factory()->create();
        $content = Content::factory()->create(['is_locked' => false]);

        $comment = $this->service->addComment($content->id, $user->id, [
            'text' => 'Anonymous comment',
            'is_anonymous' => true,
        ]);

        $this->assertTrue($comment->is_anonymous);
    }

    // --- editComment Tests ---

    public function testEditCommentWithinWindow(): void
    {
        Carbon::setTestNow(Carbon::now());

        $user = User::factory()->create();
        $comment = Comment::factory()->create([
            'author_id' => $user->id,
            'body' => 'Original text',
            'created_at' => now()->subMinutes(5),
        ]);

        $updated = $this->service->editComment($comment->id, $user->id, 'Updated text');

        $this->assertEquals('Updated text', $updated->body);
        $this->assertTrue($updated->is_edited);
        $this->assertNotNull($updated->edited_at);

        Carbon::setTestNow();
    }

    public function testEditCommentTrimsText(): void
    {
        Carbon::setTestNow(Carbon::now());

        $user = User::factory()->create();
        $comment = Comment::factory()->create([
            'author_id' => $user->id,
            'created_at' => now()->subMinutes(5),
        ]);

        $updated = $this->service->editComment($comment->id, $user->id, '  trimmed  ');

        $this->assertEquals('trimmed', $updated->body);

        Carbon::setTestNow();
    }

    public function testEditCommentFailsAfter15Minutes(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->create([
            'author_id' => $user->id,
            'created_at' => now()->subMinutes(16),
        ]);

        $this->expectException(ContentException::class);
        $this->expectExceptionMessage('Edit window has expired');

        $this->service->editComment($comment->id, $user->id, 'Too late');
    }

    public function testEditCommentFailsForNonAuthor(): void
    {
        $author = User::factory()->create();
        $otherUser = User::factory()->create();
        $comment = Comment::factory()->create([
            'author_id' => $author->id,
            'created_at' => now()->subMinutes(5),
        ]);

        $this->expectException(ContentException::class);
        $this->expectExceptionMessage('You are not the author');

        $this->service->editComment($comment->id, $otherUser->id, 'Not my comment');
    }

    public function testEditCommentAtExactly15MinutesSucceeds(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->create([
            'author_id' => $user->id,
            'created_at' => now()->subMinutes(15),
        ]);

        $updated = $this->service->editComment($comment->id, $user->id, 'Just in time');

        $this->assertEquals('Just in time', $updated->body);
    }

    // --- deleteComment Tests ---

    public function testDeleteCommentByAuthor(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->create([
            'author_id' => $user->id,
        ]);

        $this->service->deleteComment($comment->id, $user->id);

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }

    public function testDeleteCommentFailsForNonAuthor(): void
    {
        $author = User::factory()->create();
        $otherUser = User::factory()->create();
        $comment = Comment::factory()->create([
            'author_id' => $author->id,
        ]);

        $this->expectException(ContentException::class);
        $this->expectExceptionMessage('You are not the author');

        $this->service->deleteComment($comment->id, $otherUser->id);
    }

    public function testDeleteCommentWorksAtAnyAge(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->create([
            'author_id' => $user->id,
            'created_at' => now()->subDays(365),
        ]);

        $this->service->deleteComment($comment->id, $user->id);

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }

    // --- markAcceptedSolution Tests ---

    public function testMarkAcceptedSolution(): void
    {
        $contentAuthor = User::factory()->create();
        $commenter = User::factory()->create();
        $content = Content::factory()->create([
            'author_id' => $contentAuthor->id,
            'is_qna' => true,
        ]);
        $comment = Comment::factory()->create([
            'content_id' => $content->id,
            'author_id' => $commenter->id,
        ]);

        $this->service->markAcceptedSolution($comment->id, $contentAuthor->id);

        $content->refresh();
        $this->assertEquals($comment->id, $content->accepted_solution_id);
    }

    public function testMarkAcceptedSolutionFailsForNonContentAuthor(): void
    {
        $contentAuthor = User::factory()->create();
        $otherUser = User::factory()->create();
        $content = Content::factory()->create([
            'author_id' => $contentAuthor->id,
        ]);
        $comment = Comment::factory()->create([
            'content_id' => $content->id,
        ]);

        $this->expectException(ContentException::class);
        $this->expectExceptionMessage('Only the content author');

        $this->service->markAcceptedSolution($comment->id, $otherUser->id);
    }

    public function testMarkAcceptedSolutionReplacesExisting(): void
    {
        $contentAuthor = User::factory()->create();
        $content = Content::factory()->create([
            'author_id' => $contentAuthor->id,
            'is_qna' => true,
        ]);
        $comment1 = Comment::factory()->create(['content_id' => $content->id]);
        $comment2 = Comment::factory()->create(['content_id' => $content->id]);

        $this->service->markAcceptedSolution($comment1->id, $contentAuthor->id);
        $this->service->markAcceptedSolution($comment2->id, $contentAuthor->id);

        $content->refresh();
        $this->assertEquals($comment2->id, $content->accepted_solution_id);
    }

    // --- unmarkAcceptedSolution Tests ---

    public function testUnmarkAcceptedSolution(): void
    {
        $contentAuthor = User::factory()->create();
        $content = Content::factory()->create([
            'author_id' => $contentAuthor->id,
            'is_qna' => true,
        ]);
        $comment = Comment::factory()->create(['content_id' => $content->id]);

        // First mark it
        $this->service->markAcceptedSolution($comment->id, $contentAuthor->id);
        $content->refresh();
        $this->assertEquals($comment->id, $content->accepted_solution_id);

        // Now unmark it
        $this->service->unmarkAcceptedSolution($comment->id, $contentAuthor->id);
        $content->refresh();
        $this->assertNull($content->accepted_solution_id);
    }

    public function testUnmarkAcceptedSolutionFailsForNonContentAuthor(): void
    {
        $contentAuthor = User::factory()->create();
        $otherUser = User::factory()->create();
        $content = Content::factory()->create([
            'author_id' => $contentAuthor->id,
        ]);
        $comment = Comment::factory()->create(['content_id' => $content->id]);

        $this->expectException(ContentException::class);
        $this->expectExceptionMessage('Only the content author');

        $this->service->unmarkAcceptedSolution($comment->id, $otherUser->id);
    }

    // --- getThreadedComments Tests ---

    public function testGetThreadedCommentsReturnsEmptyForNoComments(): void
    {
        $content = Content::factory()->create();

        $result = $this->service->getThreadedComments($content->id);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetThreadedCommentsBuildsTree(): void
    {
        $user = User::factory()->create();
        $content = Content::factory()->create();

        $root = Comment::factory()->create([
            'content_id' => $content->id,
            'author_id' => $user->id,
            'depth' => 0,
            'parent_id' => null,
        ]);

        $reply = Comment::factory()->create([
            'content_id' => $content->id,
            'author_id' => $user->id,
            'parent_id' => $root->id,
            'depth' => 1,
        ]);

        $result = $this->service->getThreadedComments($content->id);

        $this->assertCount(1, $result);
        $this->assertEquals($root->id, $result[0]['id']);
        $this->assertCount(1, $result[0]['replies']);
        $this->assertEquals($reply->id, $result[0]['replies'][0]['id']);
    }

    public function testGetThreadedCommentsHidesAnonymousAuthors(): void
    {
        $user = User::factory()->create(['name' => 'John Doe']);
        $content = Content::factory()->create();

        Comment::factory()->create([
            'content_id' => $content->id,
            'author_id' => $user->id,
            'is_anonymous' => true,
            'depth' => 0,
            'parent_id' => null,
        ]);

        $result = $this->service->getThreadedComments($content->id);

        $this->assertCount(1, $result);
        $this->assertEquals('Anonymous Member', $result[0]['author']['name']);
        $this->assertNull($result[0]['author']['id']);
    }

    public function testGetThreadedCommentsShowsAcceptedSolutionFlag(): void
    {
        $user = User::factory()->create();
        $content = Content::factory()->create();

        $comment = Comment::factory()->create([
            'content_id' => $content->id,
            'author_id' => $user->id,
            'depth' => 0,
            'parent_id' => null,
        ]);

        $content->update(['accepted_solution_id' => $comment->id]);

        $result = $this->service->getThreadedComments($content->id);

        $this->assertTrue($result[0]['is_accepted_solution']);
    }

    // --- Reputation Point Tests ---

    public function testMarkAcceptedSolutionAwardsReputationPoints(): void
    {
        $contentAuthor = User::factory()->create();
        $commenter = User::factory()->create(['reputation_points' => 0]);
        $content = Content::factory()->create([
            'author_id' => $contentAuthor->id,
            'is_qna' => true,
        ]);
        $comment = Comment::factory()->create([
            'content_id' => $content->id,
            'author_id' => $commenter->id,
        ]);

        $this->service->markAcceptedSolution($comment->id, $contentAuthor->id);

        $commenter->refresh();
        $this->assertEquals(50, $commenter->reputation_points);
    }

    public function testUnmarkAcceptedSolutionRevokesReputationPoints(): void
    {
        $contentAuthor = User::factory()->create();
        $commenter = User::factory()->create(['reputation_points' => 100]);
        $content = Content::factory()->create([
            'author_id' => $contentAuthor->id,
            'is_qna' => true,
        ]);
        $comment = Comment::factory()->create([
            'content_id' => $content->id,
            'author_id' => $commenter->id,
        ]);

        // Mark first
        $this->service->markAcceptedSolution($comment->id, $contentAuthor->id);
        $commenter->refresh();
        $this->assertEquals(150, $commenter->reputation_points);

        // Unmark
        $this->service->unmarkAcceptedSolution($comment->id, $contentAuthor->id);
        $commenter->refresh();
        $this->assertEquals(100, $commenter->reputation_points);
    }

    public function testChangeAcceptedSolutionTransfersReputationPoints(): void
    {
        $contentAuthor = User::factory()->create();
        $commenter1 = User::factory()->create(['reputation_points' => 0]);
        $commenter2 = User::factory()->create(['reputation_points' => 0]);
        $content = Content::factory()->create([
            'author_id' => $contentAuthor->id,
            'is_qna' => true,
        ]);
        $comment1 = Comment::factory()->create([
            'content_id' => $content->id,
            'author_id' => $commenter1->id,
        ]);
        $comment2 = Comment::factory()->create([
            'content_id' => $content->id,
            'author_id' => $commenter2->id,
        ]);

        // Mark comment1 as accepted
        $this->service->markAcceptedSolution($comment1->id, $contentAuthor->id);
        $commenter1->refresh();
        $commenter2->refresh();
        $this->assertEquals(50, $commenter1->reputation_points);
        $this->assertEquals(0, $commenter2->reputation_points);

        // Change to comment2 - should transfer points
        $this->service->markAcceptedSolution($comment2->id, $contentAuthor->id);
        $commenter1->refresh();
        $commenter2->refresh();
        $this->assertEquals(0, $commenter1->reputation_points);
        $this->assertEquals(50, $commenter2->reputation_points);
    }

    public function testRemarkingSameCommentDoesNotDoublePoints(): void
    {
        $contentAuthor = User::factory()->create();
        $commenter = User::factory()->create(['reputation_points' => 0]);
        $content = Content::factory()->create([
            'author_id' => $contentAuthor->id,
            'is_qna' => true,
        ]);
        $comment = Comment::factory()->create([
            'content_id' => $content->id,
            'author_id' => $commenter->id,
        ]);

        // Mark as accepted
        $this->service->markAcceptedSolution($comment->id, $contentAuthor->id);
        $commenter->refresh();
        $this->assertEquals(50, $commenter->reputation_points);

        // Mark same comment again (idempotent)
        $this->service->markAcceptedSolution($comment->id, $contentAuthor->id);
        $commenter->refresh();
        $this->assertEquals(50, $commenter->reputation_points);
    }

    public function testReputationCanGoNegative(): void
    {
        $contentAuthor = User::factory()->create();
        $commenter = User::factory()->create(['reputation_points' => 0]);
        $content = Content::factory()->create([
            'author_id' => $contentAuthor->id,
            'is_qna' => true,
        ]);
        $comment = Comment::factory()->create([
            'content_id' => $content->id,
            'author_id' => $commenter->id,
        ]);

        // Mark and then unmark
        $this->service->markAcceptedSolution($comment->id, $contentAuthor->id);
        $this->service->unmarkAcceptedSolution($comment->id, $contentAuthor->id);

        $commenter->refresh();
        $this->assertEquals(0, $commenter->reputation_points);
    }

    public function testMultipleThreadsAwardIndependentReputation(): void
    {
        $contentAuthor = User::factory()->create();
        $commenter = User::factory()->create(['reputation_points' => 0]);

        $content1 = Content::factory()->create([
            'author_id' => $contentAuthor->id,
            'is_qna' => true,
        ]);
        $content2 = Content::factory()->create([
            'author_id' => $contentAuthor->id,
            'is_qna' => true,
        ]);

        $comment1 = Comment::factory()->create([
            'content_id' => $content1->id,
            'author_id' => $commenter->id,
        ]);
        $comment2 = Comment::factory()->create([
            'content_id' => $content2->id,
            'author_id' => $commenter->id,
        ]);

        $this->service->markAcceptedSolution($comment1->id, $contentAuthor->id);
        $this->service->markAcceptedSolution($comment2->id, $contentAuthor->id);

        $commenter->refresh();
        $this->assertEquals(100, $commenter->reputation_points);
    }
}
