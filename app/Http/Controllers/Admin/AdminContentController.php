<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ContentCategory;
use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\Tag;
use App\Services\SanitizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminContentController extends Controller
{
    public function __construct(
        protected SanitizationService $sanitizationService
    ) {}

    public function index(Request $request): Response
    {
        $query = Content::withTrashed()->with(['author:id,name,email', 'tags']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%");
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($request->has('trashed') && $request->input('trashed') === 'only') {
            $query->onlyTrashed();
        }

        $contents = $query->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Content/Index', [
            'contents' => $contents,
            'filters' => $request->only(['search', 'category', 'status', 'trashed']),
            'categories' => ContentCategory::cases(),
            'statuses' => ContentStatus::cases(),
        ]);
    }

    public function edit(string $id): Response
    {
        $content = Content::withTrashed()->with(['tags', 'author:id,name'])->findOrFail($id);

        return Inertia::render('Admin/Content/Edit', [
            'content' => $content,
            'categories' => ContentCategory::cases(),
            'statuses' => ContentStatus::cases(),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $content = Content::withTrashed()->findOrFail($id);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'min:10'],
            'category' => ['required', Rule::enum(ContentCategory::class)],
            'status' => ['required', Rule::enum(ContentStatus::class)],
            'is_locked' => ['required', 'boolean'],
            'is_anonymous' => ['required', 'boolean'],
            'is_qna' => ['required', 'boolean'],
        ]);

        $cleanHtml = $this->sanitizationService->sanitize($validated['body']);

        $content->update([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'body_html' => $cleanHtml,
            'category' => $validated['category'],
            'status' => $validated['status'],
            'is_locked' => $validated['is_locked'],
            'is_anonymous' => $validated['is_anonymous'],
            'is_qna' => $validated['is_qna'],
        ]);

        return redirect()->route('admin.content.index')->with('status', 'Content updated successfully by Admin.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $content = Content::findOrFail($id);
        $content->delete();

        return redirect()->back()->with('status', 'Content deleted successfully.');
    }

    public function restore(string $id): RedirectResponse
    {
        $content = Content::withTrashed()->findOrFail($id);
        $content->restore();

        return redirect()->back()->with('status', 'Content restored successfully.');
    }

    public function toggleLock(string $id): RedirectResponse
    {
        $content = Content::withTrashed()->findOrFail($id);

        $content->update([
            'is_locked' => !$content->is_locked,
            'locked_at' => !$content->is_locked ? now() : null,
        ]);

        $statusStr = $content->is_locked ? 'locked' : 'unlocked';
        return redirect()->back()->with('status', "Content has been {$statusStr}.");
    }
}
