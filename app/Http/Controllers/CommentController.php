<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\CommentServiceInterface;
use App\Exceptions\ContentException;
use App\Http\Requests\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Content;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function __construct(
        protected CommentServiceInterface $commentService
    ) {}

    /**
     * Get threaded comments for a content item.
     * GET /content/{content}/comments
     */
    public function index(string $content): JsonResponse
    {
        Content::findOrFail($content);

        $comments = $this->commentService->getThreadedComments($content);

        return response()->json([
            'data' => $comments,
        ]);
    }

    /**
     * Store a new comment on content.
     * POST /content/{content}/comments
     */
    public function store(StoreCommentRequest $request, string $content): JsonResponse
    {
        $this->authorize('create', Comment::class);

        $data = $request->validated();
        $authorId = $request->user()->id;

        try {
            $comment = $this->commentService->addComment($content, $authorId, $data);

            return response()->json([
                'data' => $comment->load('author'),
                'message' => 'Comment added successfully.',
            ], 201);
        } catch (ContentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode());
        }
    }

    /**
     * Update an existing comment (within 15-minute edit window).
     * PUT /comments/{comment}
     */
    public function update(Request $request, string $comment): JsonResponse
    {
        $request->validate([
            'text' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $text = trim($request->input('text'));

        if (strlen($text) < 1 || strlen($text) > 5000) {
            return response()->json([
                'message' => 'Comment must be between 1 and 5000 characters after trimming.',
            ], 422);
        }

        $authorId = $request->user()->id;

        try {
            $updatedComment = $this->commentService->editComment($comment, $authorId, $text);

            return response()->json([
                'data' => $updatedComment,
                'message' => 'Comment updated successfully.',
            ]);
        } catch (ContentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode());
        }
    }

    /**
     * Delete a comment (soft-delete, author can delete anytime).
     * DELETE /comments/{comment}
     */
    public function destroy(Request $request, string $comment): JsonResponse
    {
        $authorId = $request->user()->id;

        try {
            $this->commentService->deleteComment($comment, $authorId);

            return response()->json([
                'message' => 'Comment deleted successfully.',
            ]);
        } catch (ContentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode());
        }
    }

    /**
     * Mark a comment as the accepted solution.
     * POST /comments/{comment}/accept
     */
    public function accept(Request $request, string $comment): JsonResponse
    {
        $contentAuthorId = $request->user()->id;

        try {
            $this->commentService->markAcceptedSolution($comment, $contentAuthorId);

            return response()->json([
                'message' => 'Comment marked as accepted solution.',
            ]);
        } catch (ContentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode());
        }
    }

    /**
     * Unmark a comment as the accepted solution.
     * DELETE /comments/{comment}/accept
     */
    public function unaccept(Request $request, string $comment): JsonResponse
    {
        $contentAuthorId = $request->user()->id;

        try {
            $this->commentService->unmarkAcceptedSolution($comment, $contentAuthorId);

            return response()->json([
                'message' => 'Accepted solution unmarked.',
            ]);
        } catch (ContentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode());
        }
    }
}
