<?php

namespace App\Models;

use App\Enums\ContentCategory;
use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Content extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'contents';

    protected $fillable = [
        'author_id',
        'title',
        'body',
        'body_html',
        'category',
        'is_anonymous',
        'is_qna',
        'accepted_solution_id',
        'status',
        'is_locked',
        'locked_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => ContentCategory::class,
            'status' => ContentStatus::class,
            'is_anonymous' => 'boolean',
            'is_qna' => 'boolean',
            'is_locked' => 'boolean',
            'published_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    // Relationships

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'content_tag');
    }

    public function anonymousMetadata(): HasOne
    {
        return $this->hasOne(AnonymousMetadata::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function draft(): HasOne
    {
        return $this->hasOne(Draft::class);
    }

    // Scopes

    public function scopePublished($query)
    {
        return $query->where('status', ContentStatus::Published);
    }

    public function scopePendingReview($query)
    {
        return $query->where('status', ContentStatus::PendingReview);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', ContentStatus::Draft);
    }

    public function scopeLocked($query)
    {
        return $query->where('is_locked', true);
    }

    public function scopeUnlocked($query)
    {
        return $query->where('is_locked', false);
    }

    public function scopeByCategory($query, ContentCategory $category)
    {
        return $query->where('category', $category);
    }

    public function scopeAnonymous($query)
    {
        return $query->where('is_anonymous', true);
    }

    public function scopeQna($query)
    {
        return $query->where('is_qna', true);
    }
}
