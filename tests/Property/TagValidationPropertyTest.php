<?php

namespace Tests\Property;

use App\Enums\TagCategory;
use App\Models\Tag;
use App\Services\TagService;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property 12: Tag Validation Rules
 *
 * Tests acceptance iff: tech_stack 1-3, exactly 1 experience_level, exactly 1 category,
 * all tags exist in the database with the correct tag_category.
 *
 * Generates random tag combinations with valid/invalid counts and names.
 *
 * **Validates: Requirements 6.2, 6.3, 6.4, 6.6, 6.7**
 */
class TagValidationPropertyTest extends TestCase
{
    use TestTrait;
    use RefreshDatabase;

    private TagService $tagService;

    /**
     * Predefined tech stack tags (subset used for testing).
     */
    private const TECH_STACK_TAGS = [
        'kubernetes', 'docker', 'python', 'react', 'vue',
        'angular', 'nodejs', 'golang', 'java', 'php',
        'typescript', 'rust', 'aws', 'gcp', 'azure',
    ];

    private const EXPERIENCE_LEVELS = ['beginner', 'intermediate', 'advanced', 'architecture'];

    private const CATEGORY_TAGS = ['incident', 'architecture', 'career', 'project'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tagService = new TagService();
        $this->seedTags();
    }

    /**
     * Seed predefined tags into the database for validation checks.
     */
    private function seedTags(): void
    {
        foreach (self::TECH_STACK_TAGS as $name) {
            Tag::firstOrCreate(
                ['name' => $name],
                ['tag_category' => TagCategory::TechStack]
            );
        }

        foreach (self::EXPERIENCE_LEVELS as $name) {
            Tag::firstOrCreate(
                ['name' => $name],
                ['tag_category' => TagCategory::ExperienceLevel]
            );
        }

        foreach (self::CATEGORY_TAGS as $name) {
            Tag::firstOrCreate(
                ['name' => $name],
                ['tag_category' => TagCategory::Category]
            );
        }
    }

    /**
     * Property: Valid tag combinations (1-3 tech_stack, 1 experience_level, 1 category,
     * all existing) always pass validation.
     */
    public function testValidTagCombinationPassesValidation(): void
    {
        $this->forAll(
            Generators::choose(1, 3),
            Generators::elements(self::EXPERIENCE_LEVELS),
            Generators::elements(self::CATEGORY_TAGS)
        )
            ->then(function (int $techStackCount, string $experienceLevel, string $category) {
                // Pick random subset of tech_stack tags
                $shuffled = self::TECH_STACK_TAGS;
                shuffle($shuffled);
                $techStack = array_slice($shuffled, 0, $techStackCount);

                $tags = [
                    'tech_stack' => $techStack,
                    'experience_level' => $experienceLevel,
                    'category' => $category,
                ];

                $result = $this->tagService->validateTagSelection($tags);

                $this->assertTrue(
                    $result,
                    "Valid tag selection should pass: " . json_encode($tags)
                );
            });
    }

    /**
     * Property: Zero tech_stack tags always fails validation.
     */
    public function testZeroTechStackTagsFailsValidation(): void
    {
        $this->forAll(
            Generators::elements(self::EXPERIENCE_LEVELS),
            Generators::elements(self::CATEGORY_TAGS)
        )
            ->then(function (string $experienceLevel, string $category) {
                $tags = [
                    'tech_stack' => [],
                    'experience_level' => $experienceLevel,
                    'category' => $category,
                ];

                $result = $this->tagService->validateTagSelection($tags);

                $this->assertFalse(
                    $result,
                    "Zero tech_stack tags should fail validation"
                );
            });
    }

    /**
     * Property: More than 3 tech_stack tags always fails validation.
     */
    public function testMoreThan3TechStackTagsFailsValidation(): void
    {
        $this->forAll(
            Generators::choose(4, 10),
            Generators::elements(self::EXPERIENCE_LEVELS),
            Generators::elements(self::CATEGORY_TAGS)
        )
            ->then(function (int $techStackCount, string $experienceLevel, string $category) {
                // Pick more than 3 tech_stack tags
                $shuffled = self::TECH_STACK_TAGS;
                shuffle($shuffled);
                $techStack = array_slice($shuffled, 0, min($techStackCount, count(self::TECH_STACK_TAGS)));

                $tags = [
                    'tech_stack' => $techStack,
                    'experience_level' => $experienceLevel,
                    'category' => $category,
                ];

                $result = $this->tagService->validateTagSelection($tags);

                $this->assertFalse(
                    $result,
                    "More than 3 tech_stack tags should fail validation: count=" . count($techStack)
                );
            });
    }

    /**
     * Property: Non-existent tech_stack tag names always fail validation.
     */
    public function testNonExistentTechStackTagsFailValidation(): void
    {
        $this->forAll(
            Generators::string(),
            Generators::elements(self::EXPERIENCE_LEVELS),
            Generators::elements(self::CATEGORY_TAGS)
        )
            ->withMaxSize(20)
            ->then(function (string $randomSuffix, string $experienceLevel, string $category) {
                $fakeTechStack = 'nonexistent_tech_' . bin2hex(random_bytes(4)) . substr(md5($randomSuffix), 0, 4);

                $tags = [
                    'tech_stack' => [$fakeTechStack],
                    'experience_level' => $experienceLevel,
                    'category' => $category,
                ];

                $result = $this->tagService->validateTagSelection($tags);

                $this->assertFalse(
                    $result,
                    "Non-existent tech_stack tag '{$fakeTechStack}' should fail validation"
                );
            });
    }

    /**
     * Property: Non-existent experience_level tag always fails validation.
     */
    public function testNonExistentExperienceLevelFailsValidation(): void
    {
        $this->forAll(
            Generators::string()
        )
            ->withMaxSize(20)
            ->then(function (string $randomSuffix) {
                $fakeLevel = 'nonexistent_level_' . bin2hex(random_bytes(4)) . substr(md5($randomSuffix), 0, 4);

                $tags = [
                    'tech_stack' => ['python'],
                    'experience_level' => $fakeLevel,
                    'category' => 'incident',
                ];

                $result = $this->tagService->validateTagSelection($tags);

                $this->assertFalse(
                    $result,
                    "Non-existent experience_level '{$fakeLevel}' should fail validation"
                );
            });
    }

    /**
     * Property: Non-existent category tag always fails validation.
     */
    public function testNonExistentCategoryTagFailsValidation(): void
    {
        $this->forAll(
            Generators::string()
        )
            ->withMaxSize(20)
            ->then(function (string $randomSuffix) {
                $fakeCategory = 'nonexistent_cat_' . bin2hex(random_bytes(4)) . substr(md5($randomSuffix), 0, 4);

                $tags = [
                    'tech_stack' => ['react'],
                    'experience_level' => 'beginner',
                    'category' => $fakeCategory,
                ];

                $result = $this->tagService->validateTagSelection($tags);

                $this->assertFalse(
                    $result,
                    "Non-existent category tag '{$fakeCategory}' should fail validation"
                );
            });
    }

    /**
     * Property: Cross-category tags are rejected (e.g., using an experience_level name as tech_stack).
     */
    public function testCrossCategoryTagsAreRejected(): void
    {
        $this->forAll(
            Generators::elements(self::EXPERIENCE_LEVELS),
            Generators::elements(self::CATEGORY_TAGS)
        )
            ->then(function (string $experienceLevel, string $category) {
                // Try to use experience_level name as a tech_stack tag
                $tags = [
                    'tech_stack' => [$experienceLevel],
                    'experience_level' => $experienceLevel,
                    'category' => $category,
                ];

                $result = $this->tagService->validateTagSelection($tags);

                $this->assertFalse(
                    $result,
                    "Using experience_level '{$experienceLevel}' as tech_stack should fail (cross-category rejection)"
                );
            });
    }

    /**
     * Property: Using a category tag name as tech_stack is rejected.
     */
    public function testCategoryTagUsedAsTechStackIsRejected(): void
    {
        $this->forAll(
            Generators::elements(self::CATEGORY_TAGS),
            Generators::elements(self::EXPERIENCE_LEVELS)
        )
            ->then(function (string $categoryTagName, string $experienceLevel) {
                // 'architecture' exists in both category and might conflict
                // But it should be rejected as tech_stack regardless
                $tags = [
                    'tech_stack' => [$categoryTagName],
                    'experience_level' => $experienceLevel,
                    'category' => $categoryTagName,
                ];

                $result = $this->tagService->validateTagSelection($tags);

                $this->assertFalse(
                    $result,
                    "Using category tag '{$categoryTagName}' as tech_stack should fail (cross-category rejection)"
                );
            });
    }

    /**
     * Property: Missing tech_stack field entirely always fails validation.
     */
    public function testMissingTechStackFieldFailsValidation(): void
    {
        $this->forAll(
            Generators::elements(self::EXPERIENCE_LEVELS),
            Generators::elements(self::CATEGORY_TAGS)
        )
            ->then(function (string $experienceLevel, string $category) {
                // Missing tech_stack key entirely
                $tags = [
                    'experience_level' => $experienceLevel,
                    'category' => $category,
                ];

                $result = $this->tagService->validateTagSelection($tags);

                $this->assertFalse(
                    $result,
                    "Missing tech_stack field should fail validation"
                );
            });
    }

    /**
     * Property: Missing experience_level field always fails validation.
     */
    public function testMissingExperienceLevelFieldFailsValidation(): void
    {
        $this->forAll(
            Generators::elements(self::TECH_STACK_TAGS)
        )
            ->then(function (string $techTag) {
                $tags = [
                    'tech_stack' => [$techTag],
                    'category' => 'incident',
                ];

                $result = $this->tagService->validateTagSelection($tags);

                $this->assertFalse(
                    $result,
                    "Missing experience_level field should fail validation"
                );
            });
    }

    /**
     * Property: Missing category field always fails validation.
     */
    public function testMissingCategoryFieldFailsValidation(): void
    {
        $this->forAll(
            Generators::elements(self::TECH_STACK_TAGS)
        )
            ->then(function (string $techTag) {
                $tags = [
                    'tech_stack' => [$techTag],
                    'experience_level' => 'beginner',
                ];

                $result = $this->tagService->validateTagSelection($tags);

                $this->assertFalse(
                    $result,
                    "Missing category field should fail validation"
                );
            });
    }

    /**
     * Property: Mix of valid and non-existent tech_stack tags fails validation.
     */
    public function testMixOfValidAndInvalidTechStackFailsValidation(): void
    {
        $this->forAll(
            Generators::elements(self::TECH_STACK_TAGS),
            Generators::string()
        )
            ->withMaxSize(20)
            ->then(function (string $validTag, string $randomSuffix) {
                $fakeTag = 'fake_tech_' . bin2hex(random_bytes(4)) . substr(md5($randomSuffix), 0, 4);

                $tags = [
                    'tech_stack' => [$validTag, $fakeTag],
                    'experience_level' => 'intermediate',
                    'category' => 'career',
                ];

                $result = $this->tagService->validateTagSelection($tags);

                $this->assertFalse(
                    $result,
                    "Mix of valid '{$validTag}' and non-existent '{$fakeTag}' tech_stack tags should fail"
                );
            });
    }

    /**
     * Property: Exactly 1 tech_stack (minimum valid count) passes validation.
     */
    public function testExactlyOneTechStackTagPassesValidation(): void
    {
        $this->forAll(
            Generators::elements(self::TECH_STACK_TAGS),
            Generators::elements(self::EXPERIENCE_LEVELS),
            Generators::elements(self::CATEGORY_TAGS)
        )
            ->then(function (string $techTag, string $experienceLevel, string $category) {
                $tags = [
                    'tech_stack' => [$techTag],
                    'experience_level' => $experienceLevel,
                    'category' => $category,
                ];

                $result = $this->tagService->validateTagSelection($tags);

                $this->assertTrue(
                    $result,
                    "Exactly 1 valid tech_stack tag should pass: " . json_encode($tags)
                );
            });
    }

    /**
     * Property: Exactly 3 tech_stack (maximum valid count) passes validation.
     */
    public function testExactlyThreeTechStackTagsPassesValidation(): void
    {
        $this->forAll(
            Generators::elements(self::EXPERIENCE_LEVELS),
            Generators::elements(self::CATEGORY_TAGS)
        )
            ->then(function (string $experienceLevel, string $category) {
                $shuffled = self::TECH_STACK_TAGS;
                shuffle($shuffled);
                $techStack = array_slice($shuffled, 0, 3);

                $tags = [
                    'tech_stack' => $techStack,
                    'experience_level' => $experienceLevel,
                    'category' => $category,
                ];

                $result = $this->tagService->validateTagSelection($tags);

                $this->assertTrue(
                    $result,
                    "Exactly 3 valid tech_stack tags should pass: " . json_encode($tags)
                );
            });
    }
}
