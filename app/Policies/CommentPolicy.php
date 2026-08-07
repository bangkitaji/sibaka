<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommentPolicy
{
    use HandlesAuthorization;

    /**
     * Any authenticated user can view comments.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Only verified, non-suspended members can create comments.
     * The thread must not be locked.
     */
    public function create(User $user): bool
    {
        return $user->isActiveMember();
    }

    /**
     * Author can update their comment within 15 minutes of posting.
     */
    public function update(User $user, Comment $comment): bool
    {
        if ($comment->author_id !== $user->id) {
            return false;
        }

        // 15-minute edit window
        return $comment->created_at->diffInMinutes(now()) <= 15;
    }

    /**
     * Author can delete their comment at any time.
     * Moderators/admins can also delete any comment.
     */
    public function delete(User $user, Comment $comment): bool
    {
        if ($comment->author_id === $user->id) {
            return true;
        }

        return $user->isModerator();
    }

    /**
     * Moderator or admin can moderate comments.
     */
    public function moderate(User $user, Comment $comment): bool
    {
        return $user->isModerator();
    }

    /**
     * Only the content author can accept a solution.
     */
    public function acceptSolution(User $user, Comment $comment): bool
    {
        $content = $comment->content;

        if (!$content) {
            return false;
        }

        return $content->author_id === $user->id;
    }
}
