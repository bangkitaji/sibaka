<?php

namespace Tests\Property;

use App\Enums\ContentCategory;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Property 7: Content Category Validation
 *
 * Tests that exactly one category is required for content, and that
 * only valid category values are accepted by the validator.
 *
 * **Validates: Requirements 4.1, 4.6, 4.9, 4.10**
 */
class ContentCategoryValidationTest extends TestCase
{
    use TestTrait;

    /**
     * Valid categories as defined in the ContentCategory enum.
     */
    private const VALID_CATEGORIES = [
        'post_mortem',
        'tech_stack',
        'career_interview',
        'showcase',
    ];

    /**
     * Get the category validation rule (mirrors StoreContentRequest).
     */
    private function categoryRules(): array
    {
        return [
            'category' => ['required', 'in:post_mortem,tech_stack,career_interview,showcase'],
        ];
    }

    /**
     * Property: Any valid category from the enum always passes validation.
     *
     * For any category value drawn from the set {post_mortem, tech_stack,
     * career_interview, showcase}, the validator must pass.
     */
    public function testValidCategoryPassesValidation(): void
    {
        $this->forAll(
            Generators::elements(self::VALID_CATEGORIES)
        )
            ->then(function (string $category) {
                $data = ['category' => $category];
                $validator = Validator::make($data, $this->categoryRules());

                $this->assertTrue(
                    $validator->passes(),
                    "Valid category '{$category}' should pass validation. Errors: " . json_encode($validator->errors()->toArray())
                );
            });
    }

    /**
     * Property: Missing category always fails validation.
     *
     * When the category field is absent, null, or empty string,
     * validation must fail with an error on the 'category' field.
     */
    public function testMissingCategoryFailsValidation(): void
    {
        $missingValues = [
            [],                    // field absent
            ['category' => null],  // null value
            ['category' => ''],    // empty string
        ];

        foreach ($missingValues as $data) {
            $validator = Validator::make($data, $this->categoryRules());

            $this->assertTrue(
                $validator->fails(),
                "Missing/empty category should fail validation. Data: " . json_encode($data)
            );
            $this->assertArrayHasKey('category', $validator->errors()->toArray());
        }
    }

    /**
     * Property: Any random string NOT in the valid category set fails validation.
     *
     * For any randomly generated string that is not one of the 4 valid
     * categories, the validator must reject it.
     */
    public function testInvalidCategoryStringFailsValidation(): void
    {
        $this->forAll(
            Generators::suchThat(
                fn ($s) => !in_array($s, self::VALID_CATEGORIES, true) && strlen($s) > 0,
                Generators::string()
            )
        )
            ->withMaxSize(50)
            ->then(function (string $invalidCategory) {
                $data = ['category' => $invalidCategory];
                $validator = Validator::make($data, $this->categoryRules());

                $this->assertTrue(
                    $validator->fails(),
                    "Invalid category '{$invalidCategory}' should fail validation"
                );
                $this->assertArrayHasKey('category', $validator->errors()->toArray());
            });
    }

    /**
     * Property: Plausible but invalid category strings always fail validation.
     *
     * Tests variations like typos, case changes, and similar-looking strings
     * that are not in the valid set.
     */
    public function testPlausibleButInvalidCategoryFailsValidation(): void
    {
        $plausibleInvalid = [
            'postmortem',         // missing underscore
            'post-mortem',        // hyphen instead of underscore
            'Post_Mortem',        // wrong case
            'POST_MORTEM',        // uppercase
            'tech_stacks',        // plural
            'techstack',          // missing underscore
            'career',             // partial match
            'interview',          // partial match
            'career_interviews',  // plural
            'showcases',          // plural
            'show_case',          // extra underscore
            'incident',           // related but invalid
            'architecture',       // related but invalid
            'project',            // related but invalid
        ];

        foreach ($plausibleInvalid as $invalidCategory) {
            $data = ['category' => $invalidCategory];
            $validator = Validator::make($data, $this->categoryRules());

            $this->assertTrue(
                $validator->fails(),
                "Plausible but invalid category '{$invalidCategory}' should fail validation"
            );
            $this->assertArrayHasKey('category', $validator->errors()->toArray());
        }
    }

    /**
     * Property: The valid category set matches the ContentCategory enum values.
     *
     * Ensures the validation rule stays in sync with the enum definition.
     */
    public function testValidCategoriesMatchEnum(): void
    {
        $enumValues = array_map(
            fn (ContentCategory $case) => $case->value,
            ContentCategory::cases()
        );

        sort($enumValues);
        $validCategories = self::VALID_CATEGORIES;
        sort($validCategories);

        $this->assertEquals(
            $validCategories,
            $enumValues,
            "Validation categories must match ContentCategory enum values"
        );
    }
}
