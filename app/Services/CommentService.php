<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CommentServiceInterface;
use App\Exceptions\ContentException;
use App\Models\Comment;
use App\Models\Content;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CommentService implements CommentServiceInterface
{
    /**
     * Maximum depth for threaded comments.
     * At this depth, replies stay flat (depth remains 5).
     */
    private const MAX_DEPTH = 5;

    /**
     * Edit window in minutes.
     */
    private const EDIT_WINDOW_MINUTES = 15;

    /**
     * Add a comment to content with threading support.
     * Depth limit: 5 levels. At depth 5, replies stay at depth 5 (flat).
     */
    public function addComment(string $contentId, string $authorId, array $data): Comment
    {
        $content = Content::findOrFail($contentId);

        // Reject comments on locked threads
        if ($content->is_locked) {
            throw ContentException::locked();
        }

        $depth = 0;
        $parentId = $data['parent_id'] ?? null;

        if ($parentId) {
            $parent = Comment::findOrFail($parentId);
            // New comment depth = parent_depth + 1, capped at MAX_DEPTH
            $depth = min($parent->depth + 1, self::MAX_DEPTH);
        }

        return Comment::create([
            'content_id' => $contentId,
            'author_id' => $authorId,
            'parent_id' => $parentId,
            'body' => trim($data['text']),
            'is_anonymous' => $data['is_anonymous'] ?? false,
            'is_edited' => false,
            'depth' => $depth,
        ]);
    }

    /**
     * Edit a comment within the 15-minute edit window.
     */
    public function editComment(string $commentId, string $authorId, string $text): Comment
    {
        $comment = Comment::findOrFail($commentId);

        if ($comment->author_id !== $authorId) {
            throw ContentException::notAuthor();
        }

        // Check 15-minute edit window
        if ($comment->created_at->diffInMinutes(now()) > self::EDIT_WINDOW_MINUTES) {
            throw ContentException::editWindowExpired();
        }

        $comment->update([
            'body' => trim($text),
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        return $comment->fresh();
    }

    /**
     * Soft-delete a comment (author can delete anytime).
     */
    public function deleteComment(string $commentId, string $authorId): void
    {
        $comment = Comment::findOrFail($commentId);

        if ($comment->author_id !== $authorId) {
            throw ContentException::notAuthor();
        }

        $comment->delete();
    }

    /**
     * Mark a comment as the accepted solution.
     * Only the content author can do this. One accepted solution per thread.
     * Awards +50 reputation to solution author; transfers on change (-50 from old, +50 to new).
     */
    public function markAcceptedSolution(string $commentId, string $contentAuthorId): void
    {
        $comment = Comment::findOrFail($commentId);
        $content = Content::findOrFail($comment->content_id);

        if ($content->author_id !== $contentAuthorId) {
            throw ContentException::notContentAuthor();
        }

        DB::transaction(function () use ($content, $comment, $commentId) {
            $previousSolutionId = $content->accepted_solution_id;

            // If there's an existing accepted solution, revoke reputation from previous author
            if ($previousSolutionId && $previousSolutionId !== $commentId) {
                $previousComment = Comment::find($previousSolutionId);
                if ($previousComment) {
                    User::where('id', $previousComment->author_id)
                        ->decrement('reputation_points', 50);
                }
            }

            // Award +50 reputation to new solution author (unless it's the same comment being re-marked)
            if ($previousSolutionId !== $commentId) {
                User::where('id', $comment->author_id)
                    ->increment('reputation_points', 50);
            }

            $content->update([
                'accepted_solution_id' => $commentId,
            ]);
        });
    }

    /**
     * Unmark a comment as the accepted solution.
     * Revokes -50 reputation from the solution author.
     */
    public function unmarkAcceptedSolution(string $commentId, string $contentAuthorId): void
    {
        $comment = Comment::findOrFail($commentId);
        $content = Content::findOrFail($comment->content_id);

        if ($content->author_id !== $contentAuthorId) {
            throw ContentException::notContentAuthor();
        }

        DB::transaction(function () use ($content, $comment) {
            // Only revoke if this comment is actually the current accepted solution
            if ($content->accepted_solution_id === $comment->id) {
                User::where('id', $comment->author_id)
                    ->decrement('reputation_points', 50);
            }

            $content->update([
                'accepted_solution_id' => null,
            ]);
        });
    }

    /**
     * Get threaded comments for a content item.
     * Returns a nested array structure organized by parent-child relationships.
     */
    public function getThreadedComments(string $contentId): array
    {
        $comments = Comment::where('content_id', $contentId)
            ->with(['author'])
            ->orderBy('created_at', 'asc')
            ->get();

        $content = Content::find($contentId);
        $acceptedSolutionId = $content?->accepted_solution_id;

        // Build tree structure
        $commentMap = [];
        $roots = [];

        foreach ($comments as $comment) {
            $commentData = [
                'id' => $comment->id,
                'content_id' => $comment->content_id,
                'parent_id' => $comment->parent_id,
                'body' => $comment->body,
                'is_anonymous' => $comment->is_anonymous,
                'is_edited' => $comment->is_edited,
                'depth' => $comment->depth,
                'is_accepted_solution' => $comment->id === $acceptedSolutionId,
                'created_at' => $comment->created_at,
                'edited_at' => $comment->edited_at,
                'replies' => [],
            ];

            // Handle author display (anonymous vs identified)
            if ($comment->is_anonymous) {
                $commentData['author'] = [
                    'id' => null,
                    'name' => 'Anonymous Member',
                ];
            } else {
                $commentData['author'] = [
                    'id' => $comment->author->id,
                    'name' => $comment->author->name,
                ];
            }

            $commentMap[$comment->id] = $commentData;
        }

        // Organize into tree
        foreach ($commentMap as $id => &$commentData) {
            if ($commentData['parent_id'] === null) {
                $roots[] = &$commentMap[$id];
            } else {
                if (isset($commentMap[$commentData['parent_id']])) {
                    $commentMap[$commentData['parent_id']]['replies'][] = &$commentMap[$id];
                } else {
                    // Orphaned comment (parent deleted), treat as root
                    $roots[] = &$commentMap[$id];
                }
            }
        }
        unset($commentData);

        return $roots;
    }
}
