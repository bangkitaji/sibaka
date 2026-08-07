<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\ContentServiceInterface;
use App\Http\Requests\StoreContentRequest;
use App\Http\Requests\UpdateContentRequest;
use App\Models\Content;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContentController extends Controller
{
    public function __construct(
        protected ContentServiceInterface $contentService
    ) {}

    /**
     * Display a listing of published content.
     */
    public function index(Request $request): Response
    {
        $filters = $request->only(['category', 'tags', 'search']);

        $content = $this->contentService->listContent(
            filters: $filters,
            page: (int) $request->input('page', 1),
            perPage: 20,
            viewerId: $request->user()?->id
        );

        return Inertia::render('Content/Index', [
            'content' => $content,
            'filters' => $filters,
        ]);
    }

    /**
     * Display the specified content.
     */
    public function show(string $id): Response
    {
        $content = Content::findOrFail($id);

        $this->authorize('view', $content);

        $viewerId = auth()->id();
        $data = $this->contentService->getContent($id, $viewerId);

        return Inertia::render('Content/Show', [
            'content' => $data,
        ]);
    }

    /**
     * Show the form for creating new content.
     */
    public function create(): Response
    {
        $this->authorize('create', Content::class);

        return Inertia::render('Content/Create');
    }

    /**
     * Store a newly created content in storage.
     */
    public function store(StoreContentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $authorId = $request->user()->id;

        $content = $this->contentService->createContent($data, $authorId);

        // If publish flag is set, publish immediately
        if ($request->boolean('publish')) {
            $this->contentService->publishContent($content->id, $authorId);
        }

        return redirect()
            ->route('content.show', $content->id)
            ->with('success', 'Content created successfully.');
    }

    /**
     * Show the form for editing the specified content.
     */
    public function edit(string $id): Response
    {
        $content = Content::with('tags')->findOrFail($id);

        $this->authorize('update', $content);

        return Inertia::render('Content/Edit', [
            'content' => $content,
        ]);
    }

    /**
     * Update the specified content in storage.
     */
    public function update(UpdateContentRequest $request, string $id): RedirectResponse
    {
        $data = $request->validated();
        $authorId = $request->user()->id;

        $this->contentService->updateContent($id, $data, $authorId);

        return redirect()
            ->route('content.show', $id)
            ->with('success', 'Content updated successfully.');
    }

    /**
     * Remove the specified content from storage (soft-delete).
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $content = Content::findOrFail($id);

        $this->authorize('delete', $content);

        $reason = $request->input('reason');
        $actorId = $request->user()->id;

        $this->contentService->deleteContent($id, $actorId, $reason);

        return redirect()
            ->route('content.index')
            ->with('success', 'Content deleted successfully.');
    }
}
