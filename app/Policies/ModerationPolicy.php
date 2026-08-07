<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ModerationPolicy
{
    use HandlesAuthorization;

    /**
     * Only moderators and admins can view the moderation dashboard.
     */
    public function viewDashboard(User $user): bool
    {
        return $user->isModerator();
    }

    /**
     * Only moderators and admins can view the moderation queue.
     */
    public function viewQueue(User $user): bool
    {
        return $user->isModerator();
    }

    /**
     * Only moderators and admins can review flags.
     */
    public function reviewFlag(User $user): bool
    {
        return $user->isModerator();
    }

    /**
     * Only moderators and admins can suspend users.
     */
    public function suspendUser(User $user): bool
    {
        return $user->isModerator();
    }

    /**
     * Only moderators and admins can issue warnings.
     */
    public function issueWarning(User $user): bool
    {
        return $user->isModerator();
    }

    /**
     * Only admins can view audit logs.
     */
    public function viewAuditLogs(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Only admins can view moderation logs.
     */
    public function viewModerationLogs(User $user): bool
    {
        return $user->isAdmin();
    }
}
