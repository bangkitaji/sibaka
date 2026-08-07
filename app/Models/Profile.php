<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'job_title',
        'company',
        'years_of_experience',
        'primary_tech_stack',
        'secondary_tech_stack',
        'mentorship_status',
        'hiring_status',
        'availability',
        'linkedin_url',
        'github_url',
        'completion_percentage',
    ];

    protected function casts(): array
    {
        return [
            'years_of_experience' => 'integer',
            'completion_percentage' => 'integer',
        ];
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
