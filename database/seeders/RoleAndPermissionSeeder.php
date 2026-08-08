<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Define all Permissions
        $permissions = [
            // Admin & System Permissions
            'access-admin-panel',
            'manage-users',
            'manage-roles',
            'manage-settings',
            'manage-invite-codes',

            // Moderation Permissions
            'access-moderation-panel',
            'manage-content',
            'verify-members',
            'issue-warnings',
            'suspend-users',

            // Learning Center / Instructor (Pemateri) Permissions
            'create-course',
            'edit-own-course',
            'publish-course',
            'host-workshop',
            'manage-learning-materials',

            // Member & Learner (Peserta) Permissions
            'access-directory',
            'create-content',
            'edit-own-content',
            'delete-own-content',
            'enroll-course',
            'post-comment',
            'post-reaction',
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        // 2. Define Roles and Assign Permissions

        // Super Admin Role
        $superAdminRole = Role::findOrCreate('super-admin', 'web');
        $superAdminRole->syncPermissions(Permission::all());

        // Admin Role
        $adminRole = Role::findOrCreate('admin', 'web');
        $adminRole->syncPermissions([
            'access-admin-panel',
            'manage-users',
            'manage-roles',
            'manage-settings',
            'manage-invite-codes',
            'access-moderation-panel',
            'manage-content',
            'verify-members',
            'issue-warnings',
            'suspend-users',
            'access-directory',
            'create-content',
            'edit-own-content',
            'delete-own-content',
            'post-comment',
            'post-reaction',
        ]);

        // Moderator Role
        $moderatorRole = Role::findOrCreate('moderator', 'web');
        $moderatorRole->syncPermissions([
            'access-moderation-panel',
            'manage-content',
            'verify-members',
            'issue-warnings',
            'suspend-users',
            'access-directory',
            'create-content',
            'edit-own-content',
            'delete-own-content',
            'post-comment',
            'post-reaction',
        ]);

        // Instructor / Pemateri Role (Learning Center)
        $instructorRole = Role::findOrCreate('instructor', 'web');
        $instructorRole->syncPermissions([
            'create-course',
            'edit-own-course',
            'publish-course',
            'host-workshop',
            'manage-learning-materials',
            'access-directory',
            'create-content',
            'edit-own-content',
            'delete-own-content',
            'enroll-course',
            'post-comment',
            'post-reaction',
        ]);

        // Member / Learner (Peserta) Role
        $memberRole = Role::findOrCreate('member', 'web');
        $memberRole->syncPermissions([
            'access-directory',
            'create-content',
            'edit-own-content',
            'delete-own-content',
            'enroll-course',
            'post-comment',
            'post-reaction',
        ]);

        // Pending Role
        $pendingRole = Role::findOrCreate('pending', 'web');
        $pendingRole->syncPermissions([]);

        // 3. Migrate Existing Users to Spatie Roles
        User::chunk(100, function ($users) {
            foreach ($users as $user) {
                $roleValue = is_object($user->role) ? $user->role->value : (string) $user->role;

                match ($roleValue) {
                    'admin' => $user->syncRoles(['admin', 'member']),
                    'moderator' => $user->syncRoles(['moderator', 'member']),
                    'member' => $user->syncRoles(['member']),
                    default => $user->syncRoles(['pending']),
                };
            }
        });
    }
}
