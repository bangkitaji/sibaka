<?php

namespace Tests\Property;

use App\Enums\ContentCategory;
use App\Enums\ContentStatus;
use App\Exceptions\AnonymityException;
use App\Models\Content;
use App\Models\User;
use App\Services\ContentService;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property 10: Anonymous Content Irreversibility
 *
 * For any content that has been published with is_anonymous=true, any subsequent
 * attempt to change is_anonymous to false SHALL be rejected, and the content
 * SHALL remain anonymous.
 *
 * **Validates: Requirements 5.7**
 */
class AnonymousIrreversibilityPropertyTest extends TestCase
{
    use TestTrait;
    use RefreshDatabase;

    private ContentService $contentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contentService = new ContentService();
    }

    /**
     * Property: For any anonymous content (is_anonymous=true), any update attempt
     * setting is_anonymous=false MUST throw AnonymityException with code 403.
     *
     * Generates random update payloads that include is_anonymous=false on anonymous content.
     */
    public function testSettingIsAnonymousFalseOnAnonymousContentAlwaysThrows(): void
    {
        $this->forAll(
            Generators::elements(
                ContentCategory::PostMortem->value,
                ContentCategory::TechStack->value,
                ContentCategory::CareerInterview->value,
                ContentCategory::Showcase->value
            ),
            Generators::choose(10, 200),
            Generators::choose(10, 200)
        )
            ->withMaxSize(20)
            ->then(function (string $category, int $titleLength, int $bodyLength) {
                $user = User::factory()->member()->create();

                $content = Content::factory()->create([
                    'author_id' => $user->id,
                    'is_anonymous' => true,
                    'status' => ContentStatus::Published,
                    'category' => $category,
                ]);

                // Attempt update with is_anonymous=false and random other fields
                $updateData = [
                    'is_anonymous' => false,
                    'title' => str_repeat('t', min($titleLength, 200)),
                    'body' => str_repeat('b', min($bodyLength, 200)),
                ];

                $threw = false;
                $exceptionCode = 0;

                try {
                    $this->contentService->updateContent($content->id, $updateData, $user->id);
                } catch (AnonymityException $e) {
                    $threw = true;
                    $exceptionCode = $e->getCode();
                }

                $this->assertTrue(
                    $threw,
                    "Setting is_anonymous=false on anonymous content must throw AnonymityException"
                );
                $this->assertEquals(
                    403,
                    $exceptionCode,
                    "AnonymityException must have code 403"
                );

                // Verify content remains anonymous after rejection
                $content->refresh();
                $this->assertTrue(
                    $content->is_anonymous,
                    "Content must remain anonymous after rejected update"
                );
            });
    }

    /**
     * Property: For any anonymous content, updating with is_anonymous=true (no change)
     * should NOT throw an exception. The update should proceed normally.
     */
    public function testSettingIsAnonymousTrueOnAnonymousContentSucceeds(): void
    {
        $this->forAll(
            Generators::elements(
                ContentCategory::PostMortem->value,
                ContentCategory::TechStack->value,
                ContentCategory::CareerInterview->value,
                ContentCategory::Showcase->value
            ),
            Generators::choose(5, 100)
        )
            ->withMaxSize(20)
            ->then(function (string $category, int $titleLength) {
                $user = User::factory()->member()->create();

                $content = Content::factory()->create([
                    'author_id' => $user->id,
                    'is_anonymous' => true,
                    'status' => ContentStatus::Published,
                    'category' => $category,
                ]);

                $newTitle = str_repeat('x', min($titleLength, 100));
                $updateData = [
                    'is_anonymous' => true,
                    'title' => $newTitle,
                ];

                $threw = false;
                try {
                    $result = $this->contentService->updateContent($content->id, $updateData, $user->id);
                    // Verify the title was updated successfully
                    $this->assertEquals($newTitle, $result->title);
                } catch (AnonymityException $e) {
                    $threw = true;
                }

                $this->assertFalse(
                    $threw,
                    "Setting is_anonymous=true (no change) on anonymous content should NOT throw"
                );

                // Verify content remains anonymous
                $content->refresh();
                $this->assertTrue($content->is_anonymous);
            });
    }

    /**
     * Property: For any anonymous content, updating without setting is_anonymous at all
     * should NOT throw. Other fields (title, body, category) should update normally.
     */
    public function testUpdatingOtherFieldsOnAnonymousContentWithoutIsAnonymousSucceeds(): void
    {
        $this->forAll(
            Generators::elements(
                ContentCategory::PostMortem->value,
                ContentCategory::TechStack->value,
                ContentCategory::CareerInterview->value,
                ContentCategory::Showcase->value
            ),
            Generators::choose(1, 150),
            Generators::choose(1, 500)
        )
            ->withMaxSize(20)
            ->then(function (string $category, int $titleLength, int $bodyLength) {
                $user = User::factory()->member()->create();

                $content = Content::factory()->create([
                    'author_id' => $user->id,
                    'is_anonymous' => true,
                    'status' => ContentStatus::Published,
                    'category' => $category,
                ]);

                $newTitle = str_repeat('a', min($titleLength, 150));
                $newBody = str_repeat('c', min($bodyLength, 500));

                // Update without is_anonymous field at all
                $updateData = [
                    'title' => $newTitle,
                    'body' => $newBody,
                ];

                $threw = false;
                try {
                    $result = $this->contentService->updateContent($content->id, $updateData, $user->id);
                    $this->assertEquals($newTitle, $result->title);
                    $this->assertEquals($newBody, $result->body);
                } catch (AnonymityException $e) {
                    $threw = true;
                }

                $this->assertFalse(
                    $threw,
                    "Updating title/body on anonymous content without touching is_anonymous should NOT throw"
                );

                // Verify content remains anonymous
                $content->refresh();
                $this->assertTrue($content->is_anonymous);
            });
    }

    /**
     * Property: For non-anonymous content (is_anonymous=false), setting is_anonymous=false
     * should NOT throw. This is a no-op regarding anonymity.
     */
    public function testSettingIsAnonymousFalseOnNonAnonymousContentSucceeds(): void
    {
        $this->forAll(
            Generators::elements(
                ContentCategory::PostMortem->value,
                ContentCategory::TechStack->value,
                ContentCategory::CareerInterview->value,
                ContentCategory::Showcase->value
            ),
            Generators::choose(5, 100)
        )
            ->withMaxSize(20)
            ->then(function (string $category, int $titleLength) {
                $user = User::factory()->member()->create();

                $content = Content::factory()->create([
                    'author_id' => $user->id,
                    'is_anonymous' => false,
                    'status' => ContentStatus::Published,
                    'category' => $category,
                ]);

                $newTitle = str_repeat('z', min($titleLength, 100));
                $updateData = [
                    'is_anonymous' => false,
                    'title' => $newTitle,
                ];

                $threw = false;
                try {
                    $result = $this->contentService->updateContent($content->id, $updateData, $user->id);
                    $this->assertEquals($newTitle, $result->title);
                } catch (AnonymityException $e) {
                    $threw = true;
                }

                $this->assertFalse(
                    $threw,
                    "Setting is_anonymous=false on NON-anonymous content should NOT throw"
                );

                // Verify content remains non-anonymous
                $content->refresh();
                $this->assertFalse($content->is_anonymous);
            });
    }
}
