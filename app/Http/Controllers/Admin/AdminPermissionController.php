<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class AdminPermissionController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Permission::withCount('roles');

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('guard_name', 'like', "%{$search}%");
        }

        $permissions = $query->orderBy('name', 'asc')->paginate(15)->withQueryString();

        return Inertia::render('Admin/Permissions/Index', [
            'permissions' => $permissions,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:permissions,name'],
            'guard_name' => ['nullable', 'string', 'max:50'],
        ]);

        $permission = Permission::create([
            'name' => strtolower(trim(str_replace(' ', '-', $validated['name']))),
            'guard_name' => $validated['guard_name'] ?? 'web',
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()->back()->with('status', "Permission '{$permission->name}' created successfully.");
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $permission = Permission::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('permissions', 'name')->ignore($permission->id)],
            'guard_name' => ['nullable', 'string', 'max:50'],
        ]);

        $permission->update([
            'name' => strtolower(trim(str_replace(' ', '-', $validated['name']))),
            'guard_name' => $validated['guard_name'] ?? 'web',
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()->back()->with('status', "Permission updated to '{$permission->name}'.");
    }

    public function destroy(string $id): RedirectResponse
    {
        $permission = Permission::findOrFail($id);
        $name = $permission->name;

        // Detach from all roles first
        $permission->roles()->detach();
        $permission->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()->back()->with('status', "Permission '{$name}' deleted successfully.");
    }
}
