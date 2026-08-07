<?php

namespace App\Models;

use App\Enums\TagCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'tag_category',
    ];

    protected function casts(): array
    {
        return [
            'tag_category' => TagCategory::class,
        ];
    }

    // Relationships

    public function contents(): BelongsToMany
    {
        return $this->belongsToMany(Content::class, 'content_tag');
    }

    // Scopes

    public function scopeByCategory($query, TagCategory $category)
    {
        return $query->where('tag_category', $category);
    }

    public function scopePrefixSearch($query, string $search)
    {
        return $query->where('name', 'ilike', $search . '%');
    }
}
