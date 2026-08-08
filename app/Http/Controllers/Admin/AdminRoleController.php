<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminRoleController extends Controller
{
    public function index(Request $request): Response
    {
        $roles = Role::with('permissions')->get();
        $permissions = Permission::all();

        $users = User::with('roles')
            ->when($request->input('search'), function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'users' => $users,
            'filters' => $request->only(['search']),
        ]);
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create([
            'name' => strtolower(trim($validated['name'])),
            'guard_name' => 'web',
        ]);

        if (!empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()->back()->with('status', "Role '{$role->name}' created successfully.");
    }

    public function updateRolePermissions(Request $request, string $roleId): RedirectResponse
    {
        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::findOrFail($roleId);
        $role->syncPermissions($validated['permissions'] ?? []);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()->back()->with('status', "Permissions updated for role '{$role->name}'.");
    }

    public function assignUserRoles(Request $request, string $userId): RedirectResponse
    {
        $validated = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user = User::findOrFail($userId);

        // Prevent self-demotion from admin
        if ($user->id === $request->user()->id && !in_array('admin', $validated['roles'], true) && !in_array('super-admin', $validated['roles'], true)) {
            return redirect()->back()->with('error', 'You cannot remove admin role from yourself.');
        }

        $user->syncRoles($validated['roles']);

        // Sync legacy role column for fallback compatibility
        $primaryRole = in_array('admin', $validated['roles'], true) ? 'admin' :
                      (in_array('moderator', $validated['roles'], true) ? 'moderator' :
                      (in_array('member', $validated['roles'], true) || in_array('instructor', $validated['roles'], true) ? 'member' : 'pending'));

        $user->update(['role' => $primaryRole]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()->back()->with('status', "Roles updated for user {$user->name}.");
    }
}
