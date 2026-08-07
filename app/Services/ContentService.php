<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ContentServiceInterface;
use App\Enums\ContentCategory;
use App\Enums\ContentStatus;
use App\Jobs\AutoFlagContent;
use App\Models\Content;
use App\Models\Draft;
use App\Models\ModerationLog;
use App\Models\Tag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ContentService implements ContentServiceInterface
{
    /**
     * Create content with draft status.
     */
    public function createContent(array $data, string $authorId): Content
    {
        return DB::transaction(function () use ($data, $authorId) {
            $content = Content::create([
                'author_id' => $authorId,
                'title' => $data['title'],
                'body' => $data['body'],
                'body_html' => $data['body_html'] ?? $data['body'],
                'category' => $data['category'],
                'is_anonymous' => $data['is_anonymous'] ?? false,
                'is_qna' => $data['is_qna'] ?? false,
                'status' => ContentStatus::Draft,
            ]);

            $this->syncTags($content, $data['tags'] ?? []);

            return $content->load('tags');
        });
    }

    /**
     * Update existing content (author only).
     * Enforces anonymous content irreversibility.
     */
    public function updateContent(string $id, array $data, string $authorId): Content
    {
        return DB::transaction(function () use ($id, $data, $authorId) {
            $content = Content::where('id', $id)
                ->where('author_id', $authorId)
                ->firstOrFail();

            // Enforce irreversibility: anonymous content cannot be made non-anonymous
            if ($content->is_anonymous && isset($data['is_anonymous']) && $data['is_anonymous'] === false) {
                throw \App\Exceptions\AnonymityException::cannotRevealIdentity();
            }

            $updateData = array_filter([
                'title' => $data['title'] ?? null,
                'body' => $data['body'] ?? null,
                'body_html' => $data['body_html'] ?? $data['body'] ?? null,
                'category' => $data['category'] ?? null,
                'is_anonymous' => $data['is_anonymous'] ?? null,
                'is_qna' => $data['is_qna'] ?? null,
            ], fn ($value) => $value !== null);

            $content->update($updateData);

            if (isset($data['tags'])) {
                $this->syncTags($content, $data['tags']);
            }

            return $content->fresh(['tags']);
        });
    }

    /**
     * Publish content - change status to published and set published_at.
     * Dispatches auto-flag check for content moderation.
     */
    public function publishContent(string $id, string $authorId): Content
    {
        $content = Content::where('id', $id)
            ->where('author_id', $authorId)
            ->firstOrFail();

        $content->update([
            'status' => ContentStatus::Published,
            'published_at' => now(),
        ]);

        // Dispatch auto-flag check asynchronously
        AutoFlagContent::dispatch($content->id);

        return $content->fresh();
    }

    /**
     * Soft-delete content, log reason if moderation action.
     */
    public function deleteContent(string $id, string $actorId, ?string $reason = null): void
    {
        $content = Content::findOrFail($id);

        DB::transaction(function () use ($content, $actorId, $reason) {
            $content->delete();

            if ($reason !== null && $content->author_id !== $actorId) {
                ModerationLog::create([
                    'moderator_id' => $actorId,
                    'target_user_id' => $content->author_id,
                    'target_content_id' => $content->id,
                    'action' => 'remove_content',
                    'reason' => $reason,
                    'created_at' => now(),
                ]);
            }
        });
    }

    /**
     * Save draft body to drafts table.
     */
    public function saveDraft(string $id, string $body): void
    {
        Draft::updateOrCreate(
            ['content_id' => $id],
            [
                'author_id' => Content::findOrFail($id)->author_id,
                'body' => $body,
                'saved_at' => now(),
            ]
        );
    }

    /**
     * Retrieve saved draft body.
     */
    public function restoreDraft(string $id): ?string
    {
        $draft = Draft::where('content_id', $id)->first();

        return $draft?->body;
    }

    /**
     * Get content with author, tags, reactions summary, comments count.
     * Content with 'pending_review' status is only visible to moderators.
     */
    public function getContent(string $id, ?string $viewerId = null): ?array
    {
        $content = Content::with(['author', 'tags', 'reactions', 'comments'])
            ->find($id);

        if (!$content) {
            return null;
        }

        // Hide pending_review content from non-moderators
        if ($content->status === ContentStatus::PendingReview) {
            $isModerator = false;
            if ($viewerId) {
                $viewer = \App\Models\User::find($viewerId);
                $isModerator = $viewer && $viewer->isModerator();
            }
            if (!$isModerator) {
                return null;
            }
        }

        $reactionSummary = $content->reactions
            ->groupBy('type')
            ->map(fn ($group) => $group->count());

        $result = [
            'id' => $content->id,
            'title' => $content->title,
            'body' => $content->body,
            'body_html' => $content->body_html,
            'category' => $content->category,
            'is_anonymous' => $content->is_anonymous,
            'is_qna' => $content->is_qna,
            'is_locked' => $content->is_locked,
            'status' => $content->status,
            'published_at' => $content->published_at,
            'created_at' => $content->created_at,
            'updated_at' => $content->updated_at,
            'tags' => $content->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'tag_category' => $tag->tag_category,
            ]),
            'reactions_summary' => $reactionSummary,
            'reactions_total' => $content->reactions->count(),
            'comments_count' => $content->comments->count(),
        ];

        // Handle anonymous content - strip author info
        if ($content->is_anonymous) {
            $result['author'] = [
                'name' => 'Anonymous Member',
                'id' => null,
            ];
        } else {
            $result['author'] = [
                'id' => $content->author->id,
                'name' => $content->author->name,
            ];
        }

        // Include viewer's own reaction if authenticated
        if ($viewerId) {
            $result['viewer_reaction'] = $content->reactions
                ->where('user_id', $viewerId)
                ->first()?->type;
        }

        return $result;
    }

    /**
     * List content with filters, paginated.
     * Content with 'pending_review' status is hidden from regular members (only visible to moderators).
     */
    public function listContent(array $filters, int $page = 1, int $perPage = 20, ?string $viewerId = null): LengthAwarePaginator
    {
        $query = Content::with(['author', 'tags'])
            ->orderByDesc('published_at');

        // Determine if viewer is a moderator
        $isModerator = false;
        if ($viewerId) {
            $viewer = \App\Models\User::find($viewerId);
            $isModerator = $viewer && $viewer->isModerator();
        }

        // Regular members see only published content; moderators also see pending_review
        if ($isModerator) {
            $query->whereIn('status', [ContentStatus::Published, ContentStatus::PendingReview]);
        } else {
            $query->published();
        }

        // Filter by category
        if (!empty($filters['category'])) {
            $category = $filters['category'] instanceof ContentCategory
                ? $filters['category']
                : ContentCategory::from($filters['category']);
            $query->byCategory($category);
        }

        // Filter by tags
        if (!empty($filters['tags'])) {
            $tags = is_array($filters['tags']) ? $filters['tags'] : [$filters['tags']];
            $query->whereHas('tags', function ($q) use ($tags) {
                $q->whereIn('name', $tags);
            });
        }

        // Search by title or body
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('body', 'ilike', "%{$search}%");
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Sync tags for content based on tag data structure.
     */
    protected function syncTags(Content $content, array $tags): void
    {
        $tagIds = [];

        // Tech stack tags (1-3)
        if (!empty($tags['tech_stack'])) {
            foreach ($tags['tech_stack'] as $tagName) {
                $tag = Tag::where('name', $tagName)->first();
                if ($tag) {
                    $tagIds[] = $tag->id;
                }
            }
        }

        // Experience level tag (exactly 1)
        if (!empty($tags['experience_level'])) {
            $tag = Tag::where('name', $tags['experience_level'])->first();
            if ($tag) {
                $tagIds[] = $tag->id;
            }
        }

        // Category tag (exactly 1)
        if (!empty($tags['category'])) {
            $tag = Tag::where('name', $tags['category'])->first();
            if ($tag) {
                $tagIds[] = $tag->id;
            }
        }

        $content->tags()->sync($tagIds);
    }
}
