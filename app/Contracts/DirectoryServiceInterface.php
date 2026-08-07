<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DirectoryServiceInterface
{
    /**
     * Search alumni directory with full-text search and filters.
     */
    public function searchAlumni(string $query, array $filters, int $page = 1): LengthAwarePaginator;

    /**
     * Get a single alumni profile with user data.
     */
    public function getAlumniProfile(string $userId): ?array;

    /**
     * Update a user's profile fields and recalculate completion.
     */
    public function updateProfile(string $userId, array $data): Profile;

    /**
     * Calculate profile completion percentage and field status.
     */
    public function getProfileCompletion(string $userId): array;
}
