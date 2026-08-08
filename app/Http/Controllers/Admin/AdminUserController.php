<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('department', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        if ($verificationStatus = $request->input('verification_status')) {
            $query->where('verification_status', $verificationStatus);
        }

        if ($request->has('is_suspended') && $request->input('is_suspended') !== '') {
            $query->where('is_suspended', filter_var($request->input('is_suspended'), FILTER_VALIDATE_BOOLEAN));
        }

        $users = $query->with('roles')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $allRoles = Role::orderBy('name', 'asc')->pluck('name')->toArray();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'role', 'verification_status', 'is_suspended']),
            'roles' => $allRoles,
            'verificationStatuses' => VerificationStatus::cases(),
        ]);
    }

    public function show(string $id): Response
    {
        $user = User::with([
            'profile',
            'roles',
            'contents' => fn ($q) => $q->latest()->take(10),
            'comments' => fn ($q) => $q->latest()->take(10),
            'warnings' => fn ($q) => $q->latest(),
        ])->findOrFail($id);

        $allRoles = Role::orderBy('name', 'asc')->pluck('name')->toArray();

        return Inertia::render('Admin/Users/Show', [
            'userData' => $user,
            'roles' => $allRoles,
            'verificationStatuses' => VerificationStatus::cases(),
        ]);
    }

    public function updateRole(Request $request, string $id): RedirectResponse
    {
        $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $user = User::findOrFail($id);
        $newRole = $request->input('role');

        // Prevent admin from demoting themselves
        if ($user->id === $request->user()->id && !in_array($newRole, ['admin', 'super-admin'], true)) {
            return redirect()->back()->with('error', 'You cannot remove admin role from yourself.');
        }

        $user->syncRoles([$newRole]);

        // Sync legacy role column for fallback compatibility
        $primaryRole = in_array($newRole, ['admin', 'super-admin'], true) ? 'admin' :
                      ($newRole === 'moderator' ? 'moderator' :
                      ($newRole === 'member' || $newRole === 'instructor' ? 'member' : 'pending'));

        $user->update(['role' => $primaryRole]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()->back()->with('status', "User {$user->name}'s role updated to {$newRole}.");
    }

    public function toggleSuspension(Request $request, string $id): RedirectResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return redirect()->back()->with('error', 'You cannot suspend your own account.');
        }

        if ($user->is_suspended) {
            $user->update([
                'is_suspended' => false,
                'suspended_until' => null,
            ]);
            $message = "User {$user->name}'s suspension has been lifted.";
        } else {
            $days = $request->input('days', 7);
            $user->update([
                'is_suspended' => true,
                'suspended_until' => now()->addDays($days),
            ]);
            $message = "User {$user->name} has been suspended for {$days} days.";
        }

        return redirect()->back()->with('status', $message);
    }
}
