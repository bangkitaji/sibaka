<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContentCategory;
use App\Enums\TagCategory;
use App\Models\Tag;
use Illuminate\Support\Collection;

class TagService
{
    /**
     * Prefix-matching search for tags.
     * Minimum 2 characters query, returns max 10 results, case-insensitive.
     *
     * Optionally filter by tag category.
     */
    public function search(string $query, ?TagCategory $category = null): Collection
    {
        $trimmed = trim($query);

        if (mb_strlen($trimmed) < 2) {
            return collect();
        }

        $queryBuilder = Tag::prefixSearch($trimmed);

        if ($category !== null) {
            $queryBuilder->byCategory($category);
        }

        return $queryBuilder->limit(10)->get();
    }

    /**
     * Validate tag selection against business rules:
     * - 1-3 tech_stack tags (all must exist in predefined list)
     * - Exactly 1 experience_level tag (must exist in predefined list)
     * - Exactly 1 category tag (must exist in predefined list)
     * - All tags must exist in the database with the correct tag_category
     */
    public function validateTagSelection(array $tags): bool
    {
        // Validate tech_stack: 1-3 required
        if (empty($tags['tech_stack']) || !is_array($tags['tech_stack'])) {
            return false;
        }

        $techStackCount = count($tags['tech_stack']);
        if ($techStackCount < 1 || $techStackCount > 3) {
            return false;
        }

        // Validate experience_level: exactly 1
        if (empty($tags['experience_level']) || !is_string($tags['experience_level'])) {
            return false;
        }

        // Validate category: exactly 1
        if (empty($tags['category']) || !is_string($tags['category'])) {
            return false;
        }

        // Verify all tech_stack tags exist in the database with correct category
        $existingTechStackCount = Tag::byCategory(TagCategory::TechStack)
            ->whereIn('name', $tags['tech_stack'])
            ->count();

        if ($existingTechStackCount !== $techStackCount) {
            return false;
        }

        // Verify experience_level tag exists with correct category
        $experienceLevelExists = Tag::byCategory(TagCategory::ExperienceLevel)
            ->where('name', $tags['experience_level'])
            ->exists();

        if (!$experienceLevelExists) {
            return false;
        }

        // Verify category tag exists with correct category
        $categoryExists = Tag::byCategory(TagCategory::Category)
            ->where('name', $tags['category'])
            ->exists();

        if (!$categoryExists) {
            return false;
        }

        return true;
    }

    /**
     * Validate that the category tag matches the content's IT_Experience_Category.
     *
     * @return bool True if valid mapping, false if mismatch.
     */
    public function validateCategoryTagMapping(string $categoryTag, ContentCategory $contentCategory): bool
    {
        $expectedTag = self::categoryTagForContentCategory($contentCategory);

        return $categoryTag === $expectedTag;
    }

    /**
     * Check if a tag name exists in the predefined list (optionally filtered by category).
     */
    public function tagExists(string $name, ?TagCategory $category = null): bool
    {
        $query = Tag::where('name', $name);

        if ($category !== null) {
            $query->byCategory($category);
        }

        return $query->exists();
    }

    /**
     * Get all tags of a specific category.
     */
    public function getTagsByCategory(TagCategory $category): Collection
    {
        return Tag::byCategory($category)->orderBy('name')->get();
    }

    /**
     * Get all predefined tags grouped by category.
     */
    public function getAll(): Collection
    {
        return Tag::all()->groupBy(fn (Tag $tag) => $tag->tag_category->value);
    }

    /**
     * Get the expected category tag for a given content category.
     *
     * Mapping:
     * - PostMortem → incident
     * - TechStack → architecture
     * - CareerInterview → career
     * - Showcase → project
     */
    public static function categoryTagForContentCategory(ContentCategory $contentCategory): string
    {
        return match ($contentCategory) {
            ContentCategory::PostMortem => 'incident',
            ContentCategory::TechStack => 'architecture',
            ContentCategory::CareerInterview => 'career',
            ContentCategory::Showcase => 'project',
        };
    }
}
