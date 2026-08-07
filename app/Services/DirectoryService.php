<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\DirectoryServiceInterface;
use App\Enums\VerificationStatus;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DirectoryService implements DirectoryServiceInterface
{
    /**
     * The profile fields used for completion calculation.
     */
    private const PROFILE_FIELDS = [
        'job_title',
        'company',
        'years_of_experience',
        'primary_tech_stack',
        'secondary_tech_stack',
        'mentorship_status',
        'hiring_status',
        'availability',
    ];

    /**
     * Required fields that must be filled before completion can exceed 50%.
     */
    private const REQUIRED_FIELDS = [
        'job_title',
        'company',
        'years_of_experience',
        'primary_tech_stack',
    ];

    /**
     * Search alumni directory with PostgreSQL full-text search and filters.
     */
    public function searchAlumni(string $query, array $filters, int $page = 1): LengthAwarePaginator
    {
        $perPage = config('sibaka.directory_per_page', 20);

        $queryBuilder = Profile::query()
            ->join('users', 'profiles.user_id', '=', 'users.id')
            ->where('users.verification_status', VerificationStatus::Approved->value)
            ->select([
                'profiles.*',
                'users.name',
                'users.email',
                'users.graduation_year',
                'users.department',
            ]);

        // Full-text search using PostgreSQL GIN index
        if (!empty($query)) {
            $queryBuilder->whereRaw(
                "to_tsvector('english', coalesce(profiles.job_title, '') || ' ' || coalesce(profiles.company, '') || ' ' || coalesce(profiles.primary_tech_stack, '')) @@ plainto_tsquery('english', ?)",
                [$query]
            );
        }

        // Filter by batch (graduation year)
        if (!empty($filters['batch'])) {
            $queryBuilder->where('users.graduation_year', (int) $filters['batch']);
        }

        // Filter by job role (job_title)
        if (!empty($filters['role'])) {
            $queryBuilder->where('profiles.job_title', 'ILIKE', '%' . $filters['role'] . '%');
        }

        // Filter by tech stack
        if (!empty($filters['tech_stack'])) {
            $queryBuilder->where('profiles.primary_tech_stack', 'ILIKE', '%' . $filters['tech_stack'] . '%');
        }

        // Filter by experience level
        if (!empty($filters['experience_level'])) {
            $experienceRange = $this->getExperienceRange($filters['experience_level']);
            if ($experienceRange) {
                $queryBuilder->whereBetween('profiles.years_of_experience', $experienceRange);
            }
        }

        return $queryBuilder->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get a single alumni profile with user data.
     */
    public function getAlumniProfile(string $userId): ?array
    {
        $user = User::where('id', $userId)
            ->where('verification_status', VerificationStatus::Approved->value)
            ->with('profile')
            ->first();

        if (!$user || !$user->profile) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'graduation_year' => $user->graduation_year,
            'department' => $user->department,
            'profile' => [
                'job_title' => $user->profile->job_title,
                'company' => $user->profile->company,
                'years_of_experience' => $user->profile->years_of_experience,
                'primary_tech_stack' => $user->profile->primary_tech_stack,
                'secondary_tech_stack' => $user->profile->secondary_tech_stack,
                'mentorship_status' => $user->profile->mentorship_status,
                'hiring_status' => $user->profile->hiring_status,
                'availability' => $user->profile->availability,
                'linkedin_url' => $user->profile->linkedin_url,
                'github_url' => $user->profile->github_url,
                'completion_percentage' => $user->profile->completion_percentage,
            ],
        ];
    }

    /**
     * Update a user's profile fields and recalculate completion percentage.
     */
    public function updateProfile(string $userId, array $data): Profile
    {
        $profile = Profile::firstOrCreate(
            ['user_id' => $userId],
            ['user_id' => $userId]
        );

        $profile->fill($data);
        $profile->completion_percentage = $this->calculateCompletionPercentage($profile);
        $profile->save();

        return $profile;
    }

    /**
     * Get profile completion data including percentage and field status.
     */
    public function getProfileCompletion(string $userId): array
    {
        $profile = Profile::where('user_id', $userId)->first();

        if (!$profile) {
            return [
                'percentage' => 0,
                'filled_fields' => [],
                'missing_fields' => self::PROFILE_FIELDS,
                'required_fields_complete' => false,
            ];
        }

        $filledFields = [];
        $missingFields = [];

        foreach (self::PROFILE_FIELDS as $field) {
            if ($this->isFieldFilled($profile, $field)) {
                $filledFields[] = $field;
            } else {
                $missingFields[] = $field;
            }
        }

        $requiredFieldsComplete = $this->areRequiredFieldsFilled($profile);
        $percentage = $this->calculateCompletionPercentage($profile);

        return [
            'percentage' => $percentage,
            'filled_fields' => $filledFields,
            'missing_fields' => $missingFields,
            'required_fields_complete' => $requiredFieldsComplete,
        ];
    }

    /**
     * Calculate the completion percentage for a profile.
     *
     * Formula: (filled_fields / total_fields) * 100
     * Cap at 50% if any required field is missing.
     */
    private function calculateCompletionPercentage(Profile $profile): int
    {
        $totalFields = count(self::PROFILE_FIELDS);
        $filledCount = 0;

        foreach (self::PROFILE_FIELDS as $field) {
            if ($this->isFieldFilled($profile, $field)) {
                $filledCount++;
            }
        }

        $percentage = (int) round(($filledCount / $totalFields) * 100);

        // Cap at 50% if any required field is missing
        if (!$this->areRequiredFieldsFilled($profile)) {
            $percentage = min($percentage, 50);
        }

        return $percentage;
    }

    /**
     * Check if all required fields are filled.
     */
    private function areRequiredFieldsFilled(Profile $profile): bool
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!$this->isFieldFilled($profile, $field)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a single field is considered "filled" (not null and not empty string).
     */
    private function isFieldFilled(Profile $profile, string $field): bool
    {
        $value = $profile->getAttribute($field);

        if ($value === null) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        // years_of_experience = 0 is a valid filled value
        return true;
    }

    /**
     * Map experience level filter to years of experience range.
     */
    private function getExperienceRange(string $level): ?array
    {
        return match ($level) {
            'beginner' => [0, 2],
            'intermediate' => [3, 5],
            'advanced' => [6, 10],
            'architecture' => [11, 50],
            default => null,
        };
    }
}
