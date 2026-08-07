<?php

namespace Tests\Property;

use App\Models\Profile;
use Eris\Generators;
use Eris\TestTrait;
use Tests\TestCase;

/**
 * Property 4: Profile Completion Percentage Calculation
 *
 * Tests that percentage = (filled / total) × 100 with cap at 50% if required fields missing.
 * Generate random combinations of filled/unfilled fields.
 *
 * **Validates: Requirements 2.7**
 */
class ProfileCompletionTest extends TestCase
{
    use TestTrait;

    /**
     * Total profile fields used in completion calculation.
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
     * Helper: Create a Profile instance with specific fields filled.
     *
     * @param array $filledFields List of field names to fill with valid values
     */
    private function makeProfileWithFields(array $filledFields): Profile
    {
        $profile = new Profile();

        $fieldValues = [
            'job_title' => 'Software Engineer',
            'company' => 'Acme Corp',
            'years_of_experience' => 5,
            'primary_tech_stack' => 'PHP, Laravel',
            'secondary_tech_stack' => 'JavaScript, React',
            'mentorship_status' => 'willing',
            'hiring_status' => 'open_to_hiring',
            'availability' => 'immediate',
        ];

        foreach (self::PROFILE_FIELDS as $field) {
            if (in_array($field, $filledFields, true)) {
                $profile->setAttribute($field, $fieldValues[$field]);
            } else {
                $profile->setAttribute($field, null);
            }
        }

        return $profile;
    }

    /**
     * Helper: Calculate expected completion percentage using the spec formula.
     *
     * Formula: (filled_count / 8) * 100, rounded to int, capped at 50% if any required field is missing.
     */
    private function expectedPercentage(array $filledFields): int
    {
        $filledCount = count($filledFields);
        $rawPercentage = (int) round(($filledCount / 8) * 100);

        // Check if all required fields are filled
        $requiredComplete = empty(array_diff(self::REQUIRED_FIELDS, $filledFields));

        if (!$requiredComplete) {
            return min($rawPercentage, 50);
        }

        return $rawPercentage;
    }

    /**
     * Helper: Invoke the private calculateCompletionPercentage method on DirectoryService.
     */
    private function calculatePercentage(Profile $profile): int
    {
        $service = new \App\Services\DirectoryService();
        $reflection = new \ReflectionMethod($service, 'calculateCompletionPercentage');
        $reflection->setAccessible(true);

        return $reflection->invoke($service, $profile);
    }

    /**
     * Property: All fields filled → 100%.
     */
    public function testAllFieldsFilledGives100Percent(): void
    {
        $profile = $this->makeProfileWithFields(self::PROFILE_FIELDS);
        $percentage = $this->calculatePercentage($profile);

        $this->assertSame(100, $percentage);
    }

    /**
     * Property: No fields filled → 0%.
     */
    public function testNoFieldsFilledGives0Percent(): void
    {
        $profile = $this->makeProfileWithFields([]);
        $percentage = $this->calculatePercentage($profile);

        $this->assertSame(0, $percentage);
    }

    /**
     * Property: All required fields filled + some optional → percentage > 50%.
     */
    public function testAllRequiredFieldsFilledWithSomeOptionalGivesAbove50(): void
    {
        $optionalFields = array_diff(self::PROFILE_FIELDS, self::REQUIRED_FIELDS);

        // Generate random subsets of optional fields (at least 1 optional filled)
        $this->forAll(
            Generators::choose(1, count($optionalFields))
        )
            ->then(function (int $optionalCount) use ($optionalFields) {
                $optionalArray = array_values($optionalFields);
                // Pick the first N optional fields for determinism in test
                $selectedOptional = array_slice($optionalArray, 0, $optionalCount);
                $filledFields = array_merge(self::REQUIRED_FIELDS, $selectedOptional);

                $profile = $this->makeProfileWithFields($filledFields);
                $percentage = $this->calculatePercentage($profile);

                $this->assertGreaterThan(
                    50,
                    $percentage,
                    "With all required + {$optionalCount} optional fields filled, percentage should be > 50%. Got: {$percentage}%"
                );
            });
    }

    /**
     * Property: Some required fields missing → percentage capped at max 50%.
     *
     * Generate random combinations where at least one required field is missing.
     */
    public function testMissingRequiredFieldsCapsAt50Percent(): void
    {
        // Use a bitmask (0-255) to represent which of the 8 fields are filled.
        // Filter to only combinations where at least one required field is missing.
        $this->forAll(
            Generators::choose(0, 255)
        )
            ->withMaxSize(200)
            ->then(function (int $bitmask) {
                $filledFields = [];
                foreach (self::PROFILE_FIELDS as $i => $field) {
                    if ($bitmask & (1 << $i)) {
                        $filledFields[] = $field;
                    }
                }

                // Only test cases where at least one required field is missing
                $requiredComplete = empty(array_diff(self::REQUIRED_FIELDS, $filledFields));
                if ($requiredComplete) {
                    // Skip combinations where all required fields are present
                    return;
                }

                $profile = $this->makeProfileWithFields($filledFields);
                $percentage = $this->calculatePercentage($profile);

                $this->assertLessThanOrEqual(
                    50,
                    $percentage,
                    "With missing required fields (filled: " . implode(', ', $filledFields) . "), percentage should be ≤ 50%. Got: {$percentage}%"
                );
            });
    }

    /**
     * Property: For any random combination, percentage ∈ [0, 100].
     */
    public function testPercentageAlwaysBetween0And100(): void
    {
        $this->forAll(
            Generators::choose(0, 255) // bitmask for 8 fields
        )
            ->then(function (int $bitmask) {
                $filledFields = [];
                foreach (self::PROFILE_FIELDS as $i => $field) {
                    if ($bitmask & (1 << $i)) {
                        $filledFields[] = $field;
                    }
                }

                $profile = $this->makeProfileWithFields($filledFields);
                $percentage = $this->calculatePercentage($profile);

                $this->assertGreaterThanOrEqual(0, $percentage);
                $this->assertLessThanOrEqual(100, $percentage);
            });
    }

    /**
     * Property: percentage = min(raw_percentage, 50) if required fields incomplete, else raw_percentage.
     *
     * Tests the full formula: for any random field combination, the calculated percentage
     * matches the expected formula exactly.
     */
    public function testPercentageMatchesFormula(): void
    {
        $this->forAll(
            Generators::choose(0, 255) // bitmask for 8 fields
        )
            ->then(function (int $bitmask) {
                $filledFields = [];
                foreach (self::PROFILE_FIELDS as $i => $field) {
                    if ($bitmask & (1 << $i)) {
                        $filledFields[] = $field;
                    }
                }

                $profile = $this->makeProfileWithFields($filledFields);
                $actual = $this->calculatePercentage($profile);
                $expected = $this->expectedPercentage($filledFields);

                $this->assertSame(
                    $expected,
                    $actual,
                    "For fields [" . implode(', ', $filledFields) . "], expected {$expected}% but got {$actual}%"
                );
            });
    }

    /**
     * Property: years_of_experience = 0 counts as filled.
     */
    public function testZeroYearsOfExperienceCountsAsFilled(): void
    {
        $profile = new Profile();
        $profile->setAttribute('job_title', 'Junior Dev');
        $profile->setAttribute('company', 'Startup Inc');
        $profile->setAttribute('years_of_experience', 0);
        $profile->setAttribute('primary_tech_stack', 'Python');
        $profile->setAttribute('secondary_tech_stack', null);
        $profile->setAttribute('mentorship_status', null);
        $profile->setAttribute('hiring_status', null);
        $profile->setAttribute('availability', null);

        $percentage = $this->calculatePercentage($profile);

        // 4 fields filled out of 8 = 50%, all required fields are filled so no cap
        $this->assertSame(50, $percentage);
    }

    /**
     * Property: Empty string fields are NOT counted as filled.
     */
    public function testEmptyStringFieldsNotCountedAsFilled(): void
    {
        $profile = new Profile();
        $profile->setAttribute('job_title', '');
        $profile->setAttribute('company', '');
        $profile->setAttribute('years_of_experience', null);
        $profile->setAttribute('primary_tech_stack', '');
        $profile->setAttribute('secondary_tech_stack', '');
        $profile->setAttribute('mentorship_status', '');
        $profile->setAttribute('hiring_status', '');
        $profile->setAttribute('availability', '');

        $percentage = $this->calculatePercentage($profile);

        $this->assertSame(0, $percentage);
    }
}
