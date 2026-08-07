<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Content;
use App\Services\DraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DraftController extends Controller
{
    public function __construct(
        protected DraftService $draftService
    ) {}

    /**
     * Auto-save draft body (PUT /content/{content}/draft).
     */
    public function update(Request $request, string $content): JsonResponse
    {
        $request->validate([
            'body' => ['required', 'string'],
        ]);

        // Verify the content belongs to the authenticated user
        $contentModel = Content::where('id', $content)
            ->where('author_id', $request->user()->id)
            ->firstOrFail();

        $this->draftService->save(
            $contentModel->id,
            $request->user()->id,
            $request->input('body')
        );

        return response()->json([
            'message' => 'Draft saved.',
            'saved_at' => now()->toISOString(),
        ]);
    }

    /**
     * Restore draft body (GET /content/{content}/draft).
     */
    public function show(Request $request, string $content): JsonResponse
    {
        // Verify the content belongs to the authenticated user
        Content::where('id', $content)
            ->where('author_id', $request->user()->id)
            ->firstOrFail();

        $body = $this->draftService->restore($content);

        return response()->json([
            'body' => $body,
            'has_draft' => $body !== null,
        ]);
    }
}
