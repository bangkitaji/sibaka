<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminDepartmentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Department::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $departments = $query->ordered()->paginate(15)->withQueryString();

        return Inertia::render('Admin/Departments/Index', [
            'departments' => $departments,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:departments,code'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));

        Department::create($validated);

        return redirect()->back()->with('status', "Department '{$validated['code']}' created successfully.");
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $department = Department::findOrFail($id);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('departments', 'code')->ignore($department->id)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));

        $department->update($validated);

        return redirect()->back()->with('status', "Department '{$department->code}' updated successfully.");
    }

    public function toggleActive(string $id): RedirectResponse
    {
        $department = Department::findOrFail($id);

        $department->update([
            'is_active' => !$department->is_active,
        ]);

        $statusStr = $department->is_active ? 'activated' : 'deactivated';
        return redirect()->back()->with('status', "Department '{$department->code}' has been {$statusStr}.");
    }

    public function destroy(string $id): RedirectResponse
    {
        $department = Department::findOrFail($id);
        $code = $department->code;
        $department->delete();

        return redirect()->back()->with('status', "Department '{$code}' deleted successfully.");
    }
}
