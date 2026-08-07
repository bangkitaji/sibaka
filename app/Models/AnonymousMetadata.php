<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnonymousMetadata extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'anonymous_metadata';

    protected $fillable = [
        'content_id',
        'author_id',
        'ip_hash',
        'user_agent',
        'browser_fingerprint',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
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

    // Scopes

    public function scopeExpired($query)
    {
        return $query->where('created_at', '<', now()->subDays(90));
    }
}
