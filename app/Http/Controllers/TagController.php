<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TagCategory;
use App\Services\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function __construct(
        private readonly TagService $tagService,
    ) {}

    /**
     * Search tags by prefix. Requires minimum 2 characters.
     * Returns max 10 matching tags.
     * Optionally filter by category using ?category= parameter.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');

        if (mb_strlen(trim($query)) < 2) {
            return response()->json([]);
        }

        $category = null;
        if ($request->has('category')) {
            $category = TagCategory::tryFrom($request->input('category'));
        }

        $tags = $this->tagService->search($query, $category);

        return response()->json(
            $tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'tag_category' => $tag->tag_category->value,
            ])->values()
        );
    }
}
