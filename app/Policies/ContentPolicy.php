<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Models\Content;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContentPolicy
{
    use HandlesAuthorization;

    /**
     * Anyone can view published content listings.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Anyone can view published content; authors can view their own drafts.
     * Pending review content is only visible to moderators.
     */
    public function view(?User $user, Content $content): bool
    {
        if ($content->status === ContentStatus::Published) {
            return true;
        }

        // Pending review content is only visible to moderators
        if ($content->status === ContentStatus::PendingReview) {
            return $user !== null && $user->isModerator();
        }

        // Authors can view their own drafts/unpublished content
        if ($user && $content->author_id === $user->id) {
            return true;
        }

        // Moderators and admins can view any content
        if ($user && $user->isModerator()) {
            return true;
        }

        return false;
    }

    /**
     * Only verified, non-suspended members (or above) can create content.
     */
    public function create(User $user): bool
    {
        return $user->isActiveMember();
    }

    /**
     * Only the author can update their content, and only if the thread is not locked.
     */
    public function update(User $user, Content $content): bool
    {
        if ($content->is_locked) {
            return false;
        }

        return $content->author_id === $user->id;
    }

    /**
     * Author or moderator/admin can delete content.
     */
    public function delete(User $user, Content $content): bool
    {
        if ($content->author_id === $user->id) {
            return true;
        }

        return $user->isModerator();
    }

    /**
     * Only admins can force-delete content.
     */
    public function forceDelete(User $user, Content $content): bool
    {
        return $user->isAdmin();
    }

    /**
     * Moderator or admin can moderate content (hide, flag review, etc.).
     */
    public function moderate(User $user, Content $content): bool
    {
        return $user->isModerator();
    }

    /**
     * Moderator or admin can restore soft-deleted content.
     */
    public function restore(User $user, Content $content): bool
    {
        return $user->isModerator();
    }
}
