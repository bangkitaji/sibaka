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
 * Property 13: Tag Search Prefix Matching
 *
 * Tests that all returned tags are prefix matches (case-insensitive), max 10 results, from predefined list.
 * Generate random query strings of 2+ chars against a tag dataset.
 *
 * **Validates: Requirements 6.1**
 */
class TagSearchPropertyTest extends TestCase
{
    use TestTrait;
    use RefreshDatabase;

    private TagService $tagService;

    /**
     * All predefined tags in the system, grouped by category.
     */
    private array $predefinedTags = [
        'tech_stack' => [
            'kubernetes', 'docker', 'python', 'react', 'vue', 'angular',
            'nodejs', 'golang', 'java', 'php', 'typescript', 'rust',
            'aws', 'gcp', 'azure', 'terraform', 'postgresql', 'mongodb',
            'redis', 'elasticsearch', 'kafka', 'rabbitmq', 'nginx', 'linux', 'git',
        ],
        'experience_level' => [
            'beginner', 'intermediate', 'advanced', 'architecture',
        ],
        'category' => [
            'incident', 'architecture', 'career', 'project',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tagService = new TagService();
        $this->seedTags();
    }

    private function seedTags(): void
    {
        foreach ($this->predefinedTags as $category => $names) {
            $tagCategory = TagCategory::from($category);
            foreach ($names as $name) {
                Tag::firstOrCreate(
                    ['name' => $name],
                    ['tag_category' => $tagCategory]
                );
            }
        }
    }

    /**
     * Get all predefined tag names as a flat array.
     */
    private function getAllPredefinedTagNames(): array
    {
        return array_merge(...array_values($this->predefinedTags));
    }

    /**
     * Property: All returned tag names start with the trimmed query prefix (case-insensitive).
     *
     * For any random query of 2+ chars, every result must have a name that starts
     * with the lowercase version of the trimmed query.
     */
    public function testAllReturnedTagsArePrefixMatches(): void
    {
        $this->forAll(
            Generators::elements(...$this->getAllPredefinedTagNames()),
            Generators::choose(2, 5)
        )
            ->withMaxSize(50)
            ->then(function (string $tagName, int $prefixLen) {
                // Take a random prefix from a known tag name (ensures at least some matches)
                $query = mb_substr($tagName, 0, min($prefixLen, mb_strlen($tagName)));

                // Randomize case
                $query = rand(0, 1) ? mb_strtoupper($query) : $query;

                $results = $this->tagService->search($query);

                $trimmedQuery = mb_strtolower(trim($query));

                foreach ($results as $tag) {
                    $this->assertTrue(
                        str_starts_with(mb_strtolower($tag->name), $trimmedQuery),
                        "Tag '{$tag->name}' does not start with prefix '{$trimmedQuery}'"
                    );
                }
            });
    }

    /**
     * Property: At most 10 results returned regardless of matching count.
     *
     * Even with queries that could match many tags (e.g., short prefixes),
     * the result set never exceeds 10.
     */
    public function testMaxTenResultsReturned(): void
    {
        // Use very short prefixes that could match many tags
        $shortPrefixes = ['a', 'an', 'ar', 're', 'go', 'ja', 'ph', 'py', 'ku',
                          'do', 'no', 'vu', 'te', 'po', 'mo', 'el', 'ka', 'ra',
                          'ng', 'li', 'gi', 'aw', 'gc', 'az', 'ru', 'be', 'in',
                          'ad', 'ca', 'pr', 'inc'];

        $this->forAll(
            Generators::elements(...$shortPrefixes)
        )
            ->withMaxSize(50)
            ->then(function (string $prefix) {
                // Only test prefixes with 2+ chars (service requires minimum 2)
                if (mb_strlen($prefix) < 2) {
                    return;
                }

                $results = $this->tagService->search($prefix);

                $this->assertLessThanOrEqual(
                    10,
                    $results->count(),
                    "Search for '{$prefix}' returned more than 10 results: {$results->count()}"
                );
            });
    }

    /**
     * Property: Queries with fewer than 2 characters return empty collection.
     *
     * Single character queries and empty strings must always return no results.
     */
    public function testQueriesUnderTwoCharsReturnEmpty(): void
    {
        $this->forAll(
            Generators::elements('', ' ', 'a', 'b', 'k', 'p', 'r', '1', '@', "\t", '  ', "\n")
        )
            ->withMaxSize(50)
            ->then(function (string $shortQuery) {
                // Only test strings that are < 2 chars after trimming
                if (mb_strlen(trim($shortQuery)) >= 2) {
                    return;
                }

                $results = $this->tagService->search($shortQuery);

                $this->assertTrue(
                    $results->isEmpty(),
                    "Query '{$shortQuery}' (length " . mb_strlen(trim($shortQuery)) . ") should return empty but got {$results->count()} results"
                );
            });
    }

    /**
     * Property: All returned tags exist in the predefined list (no phantom tags).
     *
     * Every result must be one of the known predefined tag names.
     */
    public function testAllReturnedTagsExistInPredefinedList(): void
    {
        $allTagNames = $this->getAllPredefinedTagNames();

        $this->forAll(
            Generators::elements(...$allTagNames),
            Generators::choose(2, 4)
        )
            ->withMaxSize(50)
            ->then(function (string $tagName, int $prefixLen) {
                $query = mb_substr($tagName, 0, min($prefixLen, mb_strlen($tagName)));

                $results = $this->tagService->search($query);

                foreach ($results as $tag) {
                    $this->assertContains(
                        $tag->name,
                        $allTagNames,
                        "Tag '{$tag->name}' is not in the predefined tag list"
                    );
                }
            });
    }

    /**
     * Property: Category filter correctly scopes results.
     *
     * When a category filter is applied, all returned tags must belong to that category.
     */
    public function testCategoryFilterCorrectlyScopesResults(): void
    {
        $categories = TagCategory::cases();

        $this->forAll(
            Generators::elements(...$this->getAllPredefinedTagNames()),
            Generators::choose(2, 4),
            Generators::elements(...$categories)
        )
            ->withMaxSize(50)
            ->then(function (string $tagName, int $prefixLen, TagCategory $category) {
                $query = mb_substr($tagName, 0, min($prefixLen, mb_strlen($tagName)));

                $results = $this->tagService->search($query, $category);

                foreach ($results as $tag) {
                    $this->assertEquals(
                        $category,
                        $tag->tag_category,
                        "Tag '{$tag->name}' has category '{$tag->tag_category->value}' but expected '{$category->value}'"
                    );

                    // Also verify prefix match still holds
                    $trimmedQuery = mb_strtolower(trim($query));
                    $this->assertTrue(
                        str_starts_with(mb_strtolower($tag->name), $trimmedQuery),
                        "Tag '{$tag->name}' does not start with prefix '{$trimmedQuery}' when filtered by category"
                    );
                }

                // Verify max 10
                $this->assertLessThanOrEqual(10, $results->count());
            });
    }

    /**
     * Property: Whitespace-padded queries are treated as trimmed equivalents.
     *
     * Queries with leading/trailing whitespace produce the same results as trimmed versions.
     */
    public function testWhitespaceTrimmedQuerysMatchTrimmedEquivalent(): void
    {
        $this->forAll(
            Generators::elements(...$this->getAllPredefinedTagNames()),
            Generators::choose(2, 4),
            Generators::elements('  ', "\t", '   ', ' ')
        )
            ->withMaxSize(50)
            ->then(function (string $tagName, int $prefixLen, string $padding) {
                $baseQuery = mb_substr($tagName, 0, min($prefixLen, mb_strlen($tagName)));

                // Search with padding
                $paddedQuery = $padding . $baseQuery . $padding;
                $paddedResults = $this->tagService->search($paddedQuery);

                // Search without padding
                $trimmedResults = $this->tagService->search($baseQuery);

                // Both should return the same results
                $paddedNames = $paddedResults->pluck('name')->sort()->values()->toArray();
                $trimmedNames = $trimmedResults->pluck('name')->sort()->values()->toArray();

                $this->assertEquals(
                    $trimmedNames,
                    $paddedNames,
                    "Padded query '{$paddedQuery}' should return same results as trimmed '{$baseQuery}'"
                );
            });
    }
}
