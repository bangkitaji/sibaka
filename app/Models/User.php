<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'entry_year',
        'graduation_year',
        'department',
        'role',
        'verification_status',
        'failed_login_attempts',
        'locked_until',
        'is_suspended',
        'suspended_until',
        'last_login_at',
        'reputation_points',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'verification_status' => VerificationStatus::class,
            'is_suspended' => 'boolean',
            'locked_until' => 'datetime',
            'suspended_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    // Relationships

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class, 'author_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'author_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    public function inviteCodes(): HasMany
    {
        return $this->hasMany(InviteCode::class, 'generated_by');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    public function warnings(): HasMany
    {
        return $this->hasMany(Warning::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(PortalMessage::class, 'sender_id');
    }

    // Scopes

    public function scopeVerified($query)
    {
        return $query->where('verification_status', VerificationStatus::Approved);
    }

    public function scopeMembers($query)
    {
        return $query->where('role', UserRole::Member);
    }

    public function scopeModerators($query)
    {
        return $query->where('role', UserRole::Moderator);
    }

    public function scopeSuspended($query)
    {
        return $query->where('is_suspended', true);
    }

    // Role helper methods

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['admin', 'super-admin']) || ($this->role === UserRole::Admin);
    }

    public function isModerator(): bool
    {
        return $this->hasAnyRole(['moderator', 'admin', 'super-admin']) || in_array($this->role, [UserRole::Moderator, UserRole::Admin], true);
    }

    public function isMember(): bool
    {
        return $this->hasAnyRole(['member', 'instructor', 'moderator', 'admin', 'super-admin']) || in_array($this->role, [UserRole::Member, UserRole::Moderator, UserRole::Admin], true);
    }

    public function isInstructor(): bool
    {
        return $this->hasRole('instructor') || $this->hasPermissionTo('create-course');
    }

    public function isVerified(): bool
    {
        return $this->verification_status === VerificationStatus::Approved;
    }

    public function isActiveMember(): bool
    {
        return $this->isMember() && $this->isVerified() && !$this->is_suspended;
    }
}
