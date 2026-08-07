<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'content_id',
        'author_id',
        'parent_id',
        'body',
        'is_anonymous',
        'is_edited',
        'depth',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'is_edited' => 'boolean',
            'depth' => 'integer',
            'edited_at' => 'datetime',
        ];
    }

    // Relationships

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    // Scopes

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeByDepth($query, int $depth)
    {
        return $query->where('depth', $depth);
    }
}
