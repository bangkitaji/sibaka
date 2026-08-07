<?php

namespace App\Models;

use App\Enums\ModerationAction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ModerationLog extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'moderator_id',
        'target_user_id',
        'target_content_id',
        'action',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => ModerationAction::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * Boot the model and register immutability guards.
     *
     * Moderation logs are immutable: once created, they cannot be updated or deleted.
     * This is enforced at both the application level (Eloquent events) and database level
     * (PostgreSQL trigger on moderation_logs table).
     */
    protected static function booted(): void
    {
        static::updating(function (ModerationLog $log) {
            throw new LogicException('ModerationLog records are immutable and cannot be updated.');
        });

        static::deleting(function (ModerationLog $log) {
            throw new LogicException('ModerationLog records are immutable and cannot be deleted.');
        });
    }

    // Relationships

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function targetContent(): BelongsTo
    {
        return $this->belongsTo(Content::class, 'target_content_id');
    }
}
