<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Comment;

interface CommentServiceInterface
{
    /**
     * Add a comment to content, supporting threaded replies with depth limit (5 levels).
     *
     * @param string $contentId The content being commented on
     * @param string $authorId The author of the comment
     * @param array $data Comment data: text, parent_id (nullable), is_anonymous (boolean)
     * @return Comment The created comment
     *
     * @throws \App\Exceptions\ContentException If the thread is locked
     */
    public function addComment(string $contentId, string $authorId, array $data): Comment;

    /**
     * Edit a comment within the 15-minute edit window.
     *
     * @param string $commentId The comment to edit
     * @param string $authorId The author attempting the edit
     * @param string $text The new comment text
     * @return Comment The updated comment
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException If not the author or past edit window
     */
    public function editComment(string $commentId, string $authorId, string $text): Comment;

    /**
     * Soft-delete a comment (author can delete at any time).
     *
     * @param string $commentId The comment to delete
     * @param string $authorId The author attempting deletion
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException If not the author
     */
    public function deleteComment(string $commentId, string $authorId): void;

    /**
     * Mark a comment as the accepted solution (content author only, one per thread).
     *
     * @param string $commentId The comment to mark as accepted
     * @param string $contentAuthorId The content author performing the action
     */
    public function markAcceptedSolution(string $commentId, string $contentAuthorId): void;

    /**
     * Unmark a comment as the accepted solution (content author only).
     *
     * @param string $commentId The comment to unmark
     * @param string $contentAuthorId The content author performing the action
     */
    public function unmarkAcceptedSolution(string $commentId, string $contentAuthorId): void;

    /**
     * Get threaded comments for a content item, organized by parent-child relationships.
     *
     * @param string $contentId The content to retrieve comments for
     * @return array Threaded comment tree
     */
    public function getThreadedComments(string $contentId): array;
}
