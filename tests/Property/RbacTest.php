<?php

namespace Tests\Property;

use App\Enums\ContentStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Comment;
use App\Models\Content;
use App\Models\User;
use App\Policies\CommentPolicy;
use App\Policies\ContentPolicy;
use App\Policies\ModerationPolicy;
use Eris\Generators;
use Eris\TestTrait;
use Tests\TestCase;

/**
 * Property 3: Role-Based Access Control
 *
 * Tests that each role gets access to exactly its allowed actions across all
 * permission combinations. Generates random users with roles and random actions,
 * verifying authorization matches the spec.
 *
 * **Validates: Requirements 1.6, 8.8, 12.4, 12.5**
 */
class RbacTest extends TestCase
{
    use TestTrait;

    private ContentPolicy $contentPolicy;
    private CommentPolicy $commentPolicy;
    private ModerationPolicy $moderationPolicy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contentPolicy = new ContentPolicy();
        $this->commentPolicy = new CommentPolicy();
        $this->moderationPolicy = new ModerationPolicy();
    }

    /**
     * The complete access matrix defining what each role can and cannot do.
     * true = allowed, false = denied.
     */
    private function getAccessMatrix(): array
    {
        return [
            'pending' => [
                'content.viewAny' => true,
                'content.view_published' => true,
                'content.create' => false,
                // Note: update/delete policies check ownership only (not role).
                // Pending users can't create content, so in practice they have nothing
                // to update/delete. But the policy itself returns true for owners.
                'content.update_own' => true,
                'content.delete_own' => true,
                'content.delete_others' => false,
                'content.moderate' => false,
                'content.restore' => false,
                'content.forceDelete' => false,
                'comment.create' => false,
                'comment.delete_own' => true, // ownership-based, not role-based
                'comment.moderate' => false,
                'moderation.viewDashboard' => false,
                'moderation.viewQueue' => false,
                'moderation.reviewFlag' => false,
                'moderation.suspendUser' => false,
                'moderation.issueWarning' => false,
                'moderation.viewAuditLogs' => false,
                'moderation.viewModerationLogs' => false,
            ],
            'member' => [
                'content.viewAny' => true,
                'content.view_published' => true,
                'content.create' => true,
                'content.update_own' => true,
                'content.delete_own' => true,
                'content.delete_others' => false,
                'content.moderate' => false,
                'content.restore' => false,
                'content.forceDelete' => false,
                'comment.create' => true,
                'comment.delete_own' => true,
                'comment.moderate' => false,
                'moderation.viewDashboard' => false,
                'moderation.viewQueue' => false,
                'moderation.reviewFlag' => false,
                'moderation.suspendUser' => false,
                'moderation.issueWarning' => false,
                'moderation.viewAuditLogs' => false,
                'moderation.viewModerationLogs' => false,
            ],
            'moderator' => [
                'content.viewAny' => true,
                'content.view_published' => true,
                'content.create' => true,
                'content.update_own' => true,
                'content.delete_own' => true,
                'content.delete_others' => true,
                'content.moderate' => true,
                'content.restore' => true,
                'content.forceDelete' => false,
                'comment.create' => true,
                'comment.delete_own' => true,
                'comment.moderate' => true,
                'moderation.viewDashboard' => true,
                'moderation.viewQueue' => true,
                'moderation.reviewFlag' => true,
                'moderation.suspendUser' => true,
                'moderation.issueWarning' => true,
                'moderation.viewAuditLogs' => false,
                'moderation.viewModerationLogs' => false,
            ],
            'admin' => [
                'content.viewAny' => true,
                'content.view_published' => true,
                'content.create' => true,
                'content.update_own' => true,
                'content.delete_own' => true,
                'content.delete_others' => true,
                'content.moderate' => true,
                'content.restore' => true,
                'content.forceDelete' => true,
                'comment.create' => true,
                'comment.delete_own' => true,
                'comment.moderate' => true,
                'moderation.viewDashboard' => true,
                'moderation.viewQueue' => true,
                'moderation.reviewFlag' => true,
                'moderation.suspendUser' => true,
                'moderation.issueWarning' => true,
                'moderation.viewAuditLogs' => true,
                'moderation.viewModerationLogs' => true,
            ],
        ];
    }

    /**
     * Create a User model instance (not persisted) for the given role string.
     */
    private function makeUserForRole(string $role): User
    {
        $attrs = match ($role) {
            'pending' => [
                'role' => UserRole::Pending,
                'verification_status' => VerificationStatus::Pending,
                'is_suspended' => false,
            ],
            'member' => [
                'role' => UserRole::Member,
                'verification_status' => VerificationStatus::Approved,
                'is_suspended' => false,
            ],
            'moderator' => [
                'role' => UserRole::Moderator,
                'verification_status' => VerificationStatus::Approved,
                'is_suspended' => false,
            ],
            'admin' => [
                'role' => UserRole::Admin,
                'verification_status' => VerificationStatus::Approved,
                'is_suspended' => false,
            ],
        };

        $user = new User();
        $user->id = fake()->uuid();
        $user->name = fake()->name();
        $user->email = fake()->safeEmail();
        $user->role = $attrs['role'];
        $user->verification_status = $attrs['verification_status'];
        $user->is_suspended = $attrs['is_suspended'];

        return $user;
    }

    /**
     * Create a published Content model (not persisted) authored by another user.
     */
    private function makeOthersContent(): Content
    {
        $content = new Content();
        $content->id = fake()->uuid();
        $content->author_id = fake()->uuid();
        $content->status = ContentStatus::Published;
        $content->is_locked = false;

        return $content;
    }

    /**
     * Create a published Content model (not persisted) authored by the given user.
     */
    private function makeOwnContent(User $user): Content
    {
        $content = new Content();
        $content->id = fake()->uuid();
        $content->author_id = $user->id;
        $content->status = ContentStatus::Published;
        $content->is_locked = false;

        return $content;
    }

    /**
     * Create a Comment model (not persisted) authored by another user.
     */
    private function makeOthersComment(): Comment
    {
        $comment = new Comment();
        $comment->id = fake()->uuid();
        $comment->author_id = fake()->uuid();
        $comment->created_at = now();

        return $comment;
    }

    /**
     * Create a Comment model (not persisted) authored by the given user.
     */
    private function makeOwnComment(User $user): Comment
    {
        $comment = new Comment();
        $comment->id = fake()->uuid();
        $comment->author_id = $user->id;
        $comment->created_at = now(); // within 15-minute edit window

        return $comment;
    }

    /**
     * Check a specific permission for a user using policy classes directly.
     */
    private function checkPermission(
        User $user,
        string $permission,
        Content $othersContent,
        Content $ownContent,
        Comment $othersComment,
        Comment $ownComment
    ): bool {
        return match ($permission) {
            'content.viewAny' => $this->contentPolicy->viewAny($user),
            'content.view_published' => $this->contentPolicy->view($user, $othersContent),
            'content.create' => $this->contentPolicy->create($user),
            'content.update_own' => $this->contentPolicy->update($user, $ownContent),
            'content.delete_own' => $this->contentPolicy->delete($user, $ownContent),
            'content.delete_others' => $this->contentPolicy->delete($user, $othersContent),
            'content.moderate' => $this->contentPolicy->moderate($user, $othersContent),
            'content.restore' => $this->contentPolicy->restore($user, $othersContent),
            'content.forceDelete' => $this->contentPolicy->forceDelete($user, $othersContent),
            'comment.create' => $this->commentPolicy->create($user),
            'comment.delete_own' => $this->commentPolicy->delete($user, $ownComment),
            'comment.moderate' => $this->commentPolicy->moderate($user, $othersComment),
            'moderation.viewDashboard' => $this->moderationPolicy->viewDashboard($user),
            'moderation.viewQueue' => $this->moderationPolicy->viewQueue($user),
            'moderation.reviewFlag' => $this->moderationPolicy->reviewFlag($user),
            'moderation.suspendUser' => $this->moderationPolicy->suspendUser($user),
            'moderation.issueWarning' => $this->moderationPolicy->issueWarning($user),
            'moderation.viewAuditLogs' => $this->moderationPolicy->viewAuditLogs($user),
            'moderation.viewModerationLogs' => $this->moderationPolicy->viewModerationLogs($user),
            default => false,
        };
    }

    /**
     * Property: Pending users CANNOT create content.
     *
     * For any randomly generated pending user, content creation is always denied.
     */
    public function testPendingUsersCannotCreateContent(): void
    {
        $this->forAll(
            Generators::choose(1979, (int) date('Y'))
        )
            ->then(function (int $graduationYear) {
                $user = $this->makeUserForRole('pending');

                $this->assertFalse(
                    $this->contentPolicy->create($user),
                    "Pending user should NOT be able to create content"
                );

                $this->assertFalse(
                    $this->commentPolicy->create($user),
                    "Pending user should NOT be able to create comments"
                );

                $this->assertFalse(
                    $user->isActiveMember(),
                    "Pending user should NOT be an active member"
                );
            });
    }

    /**
     * Property: Members CAN create content, CAN update own, CANNOT update others'.
     *
     * For any verified, non-suspended member, content creation and own-content
     * update are allowed, but updating another user's content is denied.
     */
    public function testMembersCanCreateAndUpdateOwnButNotOthers(): void
    {
        $this->forAll(
            Generators::choose(1979, (int) date('Y'))
        )
            ->then(function (int $graduationYear) {
                $member = $this->makeUserForRole('member');
                $ownContent = $this->makeOwnContent($member);
                $othersContent = $this->makeOthersContent();

                // Members CAN create
                $this->assertTrue(
                    $this->contentPolicy->create($member),
                    "Member should be able to create content"
                );

                // Members CAN update own (non-locked)
                $this->assertTrue(
                    $this->contentPolicy->update($member, $ownContent),
                    "Member should be able to update own content"
                );

                // Members CANNOT update others'
                $this->assertFalse(
                    $this->contentPolicy->update($member, $othersContent),
                    "Member should NOT be able to update other's content"
                );
            });
    }

    /**
     * Property: Moderators CAN delete any content and CAN moderate.
     *
     * For any randomly generated moderator, deletion of any content and
     * moderation actions are always allowed.
     */
    public function testModeratorsCanDeleteAnyAndModerate(): void
    {
        $this->forAll(
            Generators::choose(1979, (int) date('Y'))
        )
            ->then(function (int $graduationYear) {
                $moderator = $this->makeUserForRole('moderator');
                $othersContent = $this->makeOthersContent();
                $othersComment = $this->makeOthersComment();

                // Moderators CAN delete any content
                $this->assertTrue(
                    $this->contentPolicy->delete($moderator, $othersContent),
                    "Moderator should be able to delete any content"
                );

                // Moderators CAN moderate content
                $this->assertTrue(
                    $this->contentPolicy->moderate($moderator, $othersContent),
                    "Moderator should be able to moderate content"
                );

                // Moderators CAN moderate comments
                $this->assertTrue(
                    $this->commentPolicy->moderate($moderator, $othersComment),
                    "Moderator should be able to moderate comments"
                );

                // Moderators CAN restore content
                $this->assertTrue(
                    $this->contentPolicy->restore($moderator, $othersContent),
                    "Moderator should be able to restore content"
                );

                // Moderators CANNOT force delete
                $this->assertFalse(
                    $this->contentPolicy->forceDelete($moderator, $othersContent),
                    "Moderator should NOT be able to force-delete content"
                );
            });
    }

    /**
     * Property: Admins CAN view audit logs, moderators CANNOT.
     *
     * For any randomly generated admin, audit log access is granted.
     * For any randomly generated moderator, audit log access is denied.
     */
    public function testAdminsCanViewAuditLogsModeratorsCanNot(): void
    {
        $this->forAll(
            Generators::choose(1979, (int) date('Y')),
            Generators::choose(1979, (int) date('Y'))
        )
            ->then(function (int $adminGradYear, int $modGradYear) {
                $admin = $this->makeUserForRole('admin');
                $moderator = $this->makeUserForRole('moderator');

                // Admins CAN view audit logs
                $this->assertTrue(
                    $this->moderationPolicy->viewAuditLogs($admin),
                    "Admin should be able to view audit logs"
                );

                // Admins CAN view moderation logs
                $this->assertTrue(
                    $this->moderationPolicy->viewModerationLogs($admin),
                    "Admin should be able to view moderation logs"
                );

                // Moderators CANNOT view audit logs
                $this->assertFalse(
                    $this->moderationPolicy->viewAuditLogs($moderator),
                    "Moderator should NOT be able to view audit logs"
                );

                // Moderators CANNOT view moderation logs
                $this->assertFalse(
                    $this->moderationPolicy->viewModerationLogs($moderator),
                    "Moderator should NOT be able to view moderation logs"
                );
            });
    }

    /**
     * Property: Suspended members CANNOT create, update, or delete content.
     *
     * For any randomly generated suspended member, all write operations
     * are denied while read access is preserved.
     */
    public function testSuspendedMembersCannotCreateUpdateDelete(): void
    {
        $this->forAll(
            Generators::choose(1979, (int) date('Y'))
        )
            ->then(function (int $graduationYear) {
                $suspendedMember = $this->makeUserForRole('member');
                $suspendedMember->is_suspended = true;

                $ownContent = $this->makeOwnContent($suspendedMember);
                $publishedContent = $this->makeOthersContent();

                // Suspended members CANNOT create content
                $this->assertFalse(
                    $this->contentPolicy->create($suspendedMember),
                    "Suspended member should NOT be able to create content"
                );

                // Suspended members CANNOT create comments
                $this->assertFalse(
                    $this->commentPolicy->create($suspendedMember),
                    "Suspended member should NOT be able to create comments"
                );

                // Suspended members are NOT active members
                $this->assertFalse(
                    $suspendedMember->isActiveMember(),
                    "Suspended member should NOT be an active member"
                );

                // Suspended members CAN still view published content (read-only)
                $this->assertTrue(
                    $this->contentPolicy->view($suspendedMember, $publishedContent),
                    "Suspended member should still be able to view published content"
                );

                // Suspended members CAN still view content listings
                $this->assertTrue(
                    $this->contentPolicy->viewAny($suspendedMember),
                    "Suspended member should still be able to view content listings"
                );
            });
    }

    /**
     * Property: For any random role, ALL permissions match the expected access matrix.
     *
     * Generates a random role and verifies every permission in the matrix matches
     * the expected authorization result.
     */
    public function testAllPermissionsMatchAccessMatrixForRandomRole(): void
    {
        $roles = ['pending', 'member', 'moderator', 'admin'];

        $this->forAll(
            Generators::elements($roles[0], $roles[1], $roles[2], $roles[3])
        )
            ->then(function (string $role) {
                $user = $this->makeUserForRole($role);
                $matrix = $this->getAccessMatrix();
                $expectedPermissions = $matrix[$role];

                $othersContent = $this->makeOthersContent();
                $ownContent = $this->makeOwnContent($user);
                $othersComment = $this->makeOthersComment();
                $ownComment = $this->makeOwnComment($user);

                foreach ($expectedPermissions as $permission => $expectedResult) {
                    $actualResult = $this->checkPermission(
                        $user,
                        $permission,
                        $othersContent,
                        $ownContent,
                        $othersComment,
                        $ownComment
                    );

                    $this->assertSame(
                        $expectedResult,
                        $actualResult,
                        "Role '{$role}' - Permission '{$permission}': expected "
                        . ($expectedResult ? 'ALLOWED' : 'DENIED')
                        . " but got " . ($actualResult ? 'ALLOWED' : 'DENIED')
                    );
                }
            });
    }
}
