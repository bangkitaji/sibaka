<?php

namespace Tests\Unit\Services;

use App\Enums\ContentCategory;
use App\Enums\TagCategory;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagServiceTest extends TestCase
{
    use RefreshDatabase;

    private TagService $tagService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tagService = new TagService();
        $this->seedTags();
    }

    private function seedTags(): void
    {
        $techStackTags = ['kubernetes', 'docker', 'python', 'react', 'vue', 'angular', 'nodejs', 'golang', 'java', 'php', 'typescript'];
        $experienceLevelTags = ['beginner', 'intermediate', 'advanced', 'architecture'];
        $categoryTags = ['incident', 'architecture', 'career', 'project'];

        foreach ($techStackTags as $name) {
            Tag::firstOrCreate(
                ['name' => $name],
                ['tag_category' => TagCategory::TechStack]
            );
        }
        foreach ($experienceLevelTags as $name) {
            Tag::firstOrCreate(
                ['name' => $name],
                ['tag_category' => TagCategory::ExperienceLevel]
            );
        }
        foreach ($categoryTags as $name) {
            Tag::firstOrCreate(
                ['name' => $name],
                ['tag_category' => TagCategory::Category]
            );
        }
    }

    // --- Search Tests ---

    public function testSearchReturnsEmptyForQueryUnder2Chars(): void
    {
        $result = $this->tagService->search('a');
        $this->assertCount(0, $result);
    }

    public function testSearchReturnsEmptyForEmptyQuery(): void
    {
        $result = $this->tagService->search('');
        $this->assertCount(0, $result);
    }

    public function testSearchReturnsEmptyForSingleWhitespace(): void
    {
        $result = $this->tagService->search(' ');
        $this->assertCount(0, $result);
    }

    public function testSearchReturnsPrefixMatchesFor2PlusChars(): void
    {
        $result = $this->tagService->search('py');
        $this->assertCount(1, $result);
        $this->assertEquals('python', $result->first()->name);
    }

    public function testSearchIsCaseInsensitive(): void
    {
        $result = $this->tagService->search('PY');
        $this->assertCount(1, $result);
        $this->assertEquals('python', $result->first()->name);
    }

    public function testSearchReturnsMax10Results(): void
    {
        // Create 15 tags starting with 'test'
        for ($i = 0; $i < 15; $i++) {
            Tag::factory()->techStack()->create(['name' => "test_tag_{$i}"]);
        }

        $result = $this->tagService->search('test');
        $this->assertCount(10, $result);
    }

    public function testSearchFiltersByCategory(): void
    {
        // 'architecture' exists in both experience_level and category
        $result = $this->tagService->search('ar', TagCategory::ExperienceLevel);
        $this->assertCount(1, $result);
        $this->assertEquals('architecture', $result->first()->name);
        $this->assertEquals(TagCategory::ExperienceLevel, $result->first()->tag_category);

        $result = $this->tagService->search('ar', TagCategory::Category);
        $this->assertCount(1, $result);
        $this->assertEquals('architecture', $result->first()->name);
        $this->assertEquals(TagCategory::Category, $result->first()->tag_category);
    }

    public function testSearchTrimsQuery(): void
    {
        $result = $this->tagService->search('  py  ');
        $this->assertCount(1, $result);
        $this->assertEquals('python', $result->first()->name);
    }

    // --- Validate Tag Selection Tests ---

    public function testValidateTagSelectionAcceptsValidSelection(): void
    {
        $tags = [
            'tech_stack' => ['python', 'react'],
            'experience_level' => 'intermediate',
            'category' => 'career',
        ];

        $this->assertTrue($this->tagService->validateTagSelection($tags));
    }

    public function testValidateTagSelectionAcceptsSingleTechStack(): void
    {
        $tags = [
            'tech_stack' => ['docker'],
            'experience_level' => 'beginner',
            'category' => 'incident',
        ];

        $this->assertTrue($this->tagService->validateTagSelection($tags));
    }

    public function testValidateTagSelectionAcceptsMaxThreeTechStack(): void
    {
        $tags = [
            'tech_stack' => ['docker', 'kubernetes', 'golang'],
            'experience_level' => 'advanced',
            'category' => 'project',
        ];

        $this->assertTrue($this->tagService->validateTagSelection($tags));
    }

    public function testValidateTagSelectionRejectsEmptyTechStack(): void
    {
        $tags = [
            'tech_stack' => [],
            'experience_level' => 'beginner',
            'category' => 'career',
        ];

        $this->assertFalse($this->tagService->validateTagSelection($tags));
    }

    public function testValidateTagSelectionRejectsMoreThanThreeTechStack(): void
    {
        $tags = [
            'tech_stack' => ['docker', 'kubernetes', 'golang', 'python'],
            'experience_level' => 'beginner',
            'category' => 'career',
        ];

        $this->assertFalse($this->tagService->validateTagSelection($tags));
    }

    public function testValidateTagSelectionRejectsNonexistentTechStackTag(): void
    {
        $tags = [
            'tech_stack' => ['nonexistent_tag'],
            'experience_level' => 'beginner',
            'category' => 'career',
        ];

        $this->assertFalse($this->tagService->validateTagSelection($tags));
    }

    public function testValidateTagSelectionRejectsNonexistentExperienceLevel(): void
    {
        $tags = [
            'tech_stack' => ['python'],
            'experience_level' => 'expert',
            'category' => 'career',
        ];

        $this->assertFalse($this->tagService->validateTagSelection($tags));
    }

    public function testValidateTagSelectionRejectsNonexistentCategoryTag(): void
    {
        $tags = [
            'tech_stack' => ['python'],
            'experience_level' => 'beginner',
            'category' => 'nonexistent',
        ];

        $this->assertFalse($this->tagService->validateTagSelection($tags));
    }

    public function testValidateTagSelectionRejectsMissingExperienceLevel(): void
    {
        $tags = [
            'tech_stack' => ['python'],
            'category' => 'career',
        ];

        $this->assertFalse($this->tagService->validateTagSelection($tags));
    }

    public function testValidateTagSelectionRejectsMissingCategory(): void
    {
        $tags = [
            'tech_stack' => ['python'],
            'experience_level' => 'beginner',
        ];

        $this->assertFalse($this->tagService->validateTagSelection($tags));
    }

    public function testValidateTagSelectionRejectsCrossCategory(): void
    {
        // 'beginner' is an experience_level tag, not tech_stack
        $tags = [
            'tech_stack' => ['beginner'],
            'experience_level' => 'intermediate',
            'category' => 'career',
        ];

        $this->assertFalse($this->tagService->validateTagSelection($tags));
    }

    // --- Category Tag Mapping Tests ---

    public function testCategoryTagForPostMortemIsIncident(): void
    {
        $result = TagService::categoryTagForContentCategory(ContentCategory::PostMortem);
        $this->assertEquals('incident', $result);
    }

    public function testCategoryTagForTechStackIsArchitecture(): void
    {
        $result = TagService::categoryTagForContentCategory(ContentCategory::TechStack);
        $this->assertEquals('architecture', $result);
    }

    public function testCategoryTagForCareerInterviewIsCareer(): void
    {
        $result = TagService::categoryTagForContentCategory(ContentCategory::CareerInterview);
        $this->assertEquals('career', $result);
    }

    public function testCategoryTagForShowcaseIsProject(): void
    {
        $result = TagService::categoryTagForContentCategory(ContentCategory::Showcase);
        $this->assertEquals('project', $result);
    }

    public function testValidateCategoryTagMappingAcceptsCorrectMapping(): void
    {
        $this->assertTrue(
            $this->tagService->validateCategoryTagMapping('incident', ContentCategory::PostMortem)
        );
        $this->assertTrue(
            $this->tagService->validateCategoryTagMapping('architecture', ContentCategory::TechStack)
        );
        $this->assertTrue(
            $this->tagService->validateCategoryTagMapping('career', ContentCategory::CareerInterview)
        );
        $this->assertTrue(
            $this->tagService->validateCategoryTagMapping('project', ContentCategory::Showcase)
        );
    }

    public function testValidateCategoryTagMappingRejectsIncorrectMapping(): void
    {
        $this->assertFalse(
            $this->tagService->validateCategoryTagMapping('career', ContentCategory::PostMortem)
        );
        $this->assertFalse(
            $this->tagService->validateCategoryTagMapping('incident', ContentCategory::Showcase)
        );
    }

    // --- tagExists Tests ---

    public function testTagExistsReturnsTrueForExistingTag(): void
    {
        $this->assertTrue($this->tagService->tagExists('python'));
    }

    public function testTagExistsReturnsFalseForNonexistentTag(): void
    {
        $this->assertFalse($this->tagService->tagExists('nonexistent_tag'));
    }

    public function testTagExistsWithCategoryFilter(): void
    {
        $this->assertTrue($this->tagService->tagExists('python', TagCategory::TechStack));
        $this->assertFalse($this->tagService->tagExists('python', TagCategory::ExperienceLevel));
    }
}
