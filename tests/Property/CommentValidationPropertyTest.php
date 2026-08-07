<?php

namespace Tests\Property;

use App\Exceptions\ContentException;
use App\Models\Content;
use App\Models\User;
use App\Services\CommentService;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Property 15: Comment Validation
 *
 * Tests that:
 * 1. A comment is accepted iff trimmed length is 1-5000 characters
 * 2. Locked threads reject ALL comment attempts regardless of text validity
 *
 * Generates random strings with leading/trailing whitespace and varied lengths.
 *
 * **Validates: Requirements 7.7, 7.8, 7.9**
 */
class CommentValidationPropertyTest extends TestCase
{
    use TestTrait;
    use RefreshDatabase;

    /**
     * Get the comment validation rules (mirrors StoreCommentRequest).
     */
    private function commentRules(): array
    {
        return [
            'text' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }

    /**
     * Simulate StoreCommentRequest's prepareForValidation by trimming text,
     * then validate against the rules.
     */
    private function prepareAndValidate(string $text): bool
    {
        $trimmed = trim($text);
        $validator = Validator::make(['text' => $trimmed], $this->commentRules());
        return $validator->passes();
    }

    /**
     * Generate random whitespace strings for padding.
     */
    private function generateWhitespace(int $length): string
    {
        $chars = [' ', "\t", "\n", "\r"];
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[array_rand($chars)];
        }
        return $result;
    }

    /**
     * Property: Comment with trimmed length 1-5000 passes validation.
     *
     * For any randomly generated body of valid length (after trim),
     * surrounded by random leading/trailing whitespace, the validator must pass.
     */
    public function testCommentWithValidTrimmedLengthPassesValidation(): void
    {
        $this->forAll(
            Generators::choose(1, 5000),    // trimmed body length
            Generators::choose(0, 10),       // leading whitespace length
            Generators::choose(0, 10)        // trailing whitespace length
        )
            ->withMaxSize(50)
            ->then(function (int $bodyLength, int $leadingWs, int $trailingWs) {
                $body = str_repeat('a', $bodyLength);
                $text = $this->generateWhitespace($leadingWs) . $body . $this->generateWhitespace($trailingWs);

                $result = $this->prepareAndValidate($text);

                $this->assertTrue(
                    $result,
                    "Comment with trimmed length {$bodyLength} (1-5000) should pass validation"
                );
            });
    }

    /**
     * Property: Comment with trimmed length 0 (whitespace-only) fails validation.
     *
     * For any randomly generated whitespace-only string, the validator must fail
     * because trim results in empty string.
     */
    public function testWhitespaceOnlyCommentFailsValidation(): void
    {
        $this->forAll(
            Generators::choose(0, 50) // whitespace length (0 = empty string)
        )
            ->then(function (int $wsLength) {
                $text = $this->generateWhitespace($wsLength);

                $result = $this->prepareAndValidate($text);

                $this->assertFalse(
                    $result,
                    "Whitespace-only comment with {$wsLength} whitespace chars should fail validation (trimmed length = 0)"
                );
            });
    }

    /**
     * Property: Comment with trimmed length exceeding 5000 fails validation.
     *
     * For any randomly generated body length > 5000, surrounded by random whitespace,
     * the validator must fail on the text field.
     */
    public function testCommentExceeding5000CharsFailsValidation(): void
    {
        $this->forAll(
            Generators::choose(5001, 6000),  // trimmed body length (over limit)
            Generators::choose(0, 10),        // leading whitespace length
            Generators::choose(0, 10)         // trailing whitespace length
        )
            ->then(function (int $bodyLength, int $leadingWs, int $trailingWs) {
                $body = str_repeat('x', $bodyLength);
                $text = $this->generateWhitespace($leadingWs) . $body . $this->generateWhitespace($trailingWs);

                $result = $this->prepareAndValidate($text);

                $this->assertFalse(
                    $result,
                    "Comment with trimmed length {$bodyLength} (>5000) should fail validation"
                );
            });
    }

    /**
     * Property: General invariant - comment accepted iff 1 ≤ len(trim(S)) ≤ 5000.
     *
     * Generate random strings of length 0-6000 with random whitespace padding,
     * and verify the acceptance matches the property.
     */
    public function testCommentValidationInvariant(): void
    {
        $this->forAll(
            Generators::choose(0, 6000),     // content body length (non-ws chars)
            Generators::choose(0, 15),        // leading whitespace length
            Generators::choose(0, 15)         // trailing whitespace length
        )
            ->withMaxSize(50)
            ->then(function (int $bodyLength, int $leadingWs, int $trailingWs) {
                $body = str_repeat('z', $bodyLength);
                $text = $this->generateWhitespace($leadingWs) . $body . $this->generateWhitespace($trailingWs);

                $trimmedLength = mb_strlen(trim($text));
                $expectedAccepted = ($trimmedLength >= 1 && $trimmedLength <= 5000);

                $result = $this->prepareAndValidate($text);

                $this->assertEquals(
                    $expectedAccepted,
                    $result,
                    "Comment with trimmed length {$trimmedLength} should be "
                    . ($expectedAccepted ? 'accepted' : 'rejected')
                    . " (valid range: 1-5000)"
                );
            });
    }

    /**
     * Property: Locked threads reject ALL comments regardless of text validity.
     *
     * For any content marked as locked and any valid comment text,
     * CommentService::addComment must throw ContentException::locked().
     *
     * This verifies the CommentService lock check behavior directly.
     * Uses the database to create locked content and verify the exception.
     */
    #[Group('database')]
    public function testLockedThreadRejectsAllComments(): void
    {
        $this->forAll(
            Generators::choose(1, 500) // valid body length (keep small for DB perf)
        )
            ->withMaxSize(5)
            ->then(function (int $bodyLength) {
                $user = User::factory()->member()->create([
                    'email' => 'locked_' . bin2hex(random_bytes(8)) . '@example.net',
                ]);
                $content = Content::factory()->create([
                    'author_id' => $user->id,
                    'is_locked' => true,
                ]);

                $commentService = new CommentService();
                $commentText = str_repeat('a', $bodyLength);

                try {
                    $commentService->addComment(
                        $content->id,
                        $user->id,
                        ['text' => $commentText]
                    );
                    $this->fail(
                        "Comment on locked thread should throw ContentException, "
                        . "but was accepted (body length: {$bodyLength})"
                    );
                } catch (ContentException $e) {
                    $this->assertEquals(
                        'Thread locked due to inactivity',
                        $e->getMessage(),
                        "Locked thread should throw 'Thread locked due to inactivity'"
                    );
                }
            });
    }

    /**
     * Property: Locked threads reject comments even with empty/whitespace text.
     *
     * The lock check occurs BEFORE text validation in CommentService,
     * so even invalid text on locked threads should hit the lock error first.
     */
    #[Group('database')]
    public function testLockedThreadRejectsEvenInvalidComments(): void
    {
        $this->forAll(
            Generators::choose(0, 20) // whitespace-only length (would fail validation)
        )
            ->withMaxSize(5)
            ->then(function (int $wsLength) {
                $user = User::factory()->member()->create([
                    'email' => 'invalid_' . bin2hex(random_bytes(8)) . '@example.net',
                ]);
                $content = Content::factory()->create([
                    'author_id' => $user->id,
                    'is_locked' => true,
                ]);

                $commentService = new CommentService();
                $commentText = $this->generateWhitespace($wsLength);

                try {
                    $commentService->addComment(
                        $content->id,
                        $user->id,
                        ['text' => $commentText]
                    );
                    $this->fail(
                        "Comment on locked thread should throw ContentException even with invalid text"
                    );
                } catch (ContentException $e) {
                    $this->assertEquals(
                        'Thread locked due to inactivity',
                        $e->getMessage(),
                        "Locked thread should reject before text validation"
                    );
                }
            });
    }

    /**
     * Property: Unlocked threads accept valid comments (service-level).
     *
     * For any content NOT locked and a valid comment text,
     * CommentService::addComment should succeed and return a Comment.
     */
    #[Group('database')]
    public function testUnlockedThreadAcceptsValidComments(): void
    {
        $this->forAll(
            Generators::choose(1, 500) // valid body length (keep small for DB perf)
        )
            ->withMaxSize(5)
            ->then(function (int $bodyLength) {
                $user = User::factory()->member()->create([
                    'email' => 'test_' . bin2hex(random_bytes(8)) . '@example.net',
                ]);
                $content = Content::factory()->create([
                    'author_id' => $user->id,
                    'is_locked' => false,
                ]);

                $commentService = new CommentService();
                $commentText = str_repeat('c', $bodyLength);

                $comment = $commentService->addComment(
                    $content->id,
                    $user->id,
                    ['text' => $commentText]
                );

                $this->assertNotNull($comment, "Comment should be created on unlocked thread");
                $this->assertEquals(
                    $commentText,
                    $comment->body,
                    "Comment body should match the trimmed text"
                );
            });
    }

    /**
     * Property: Boundary - exactly 5000 chars passes validation.
     */
    public function testCommentExactly5000CharsPassesValidation(): void
    {
        $text = str_repeat('b', 5000);
        $result = $this->prepareAndValidate($text);

        $this->assertTrue(
            $result,
            "Comment with exactly 5000 chars should pass validation"
        );
    }

    /**
     * Property: Boundary - exactly 5001 chars fails validation.
     */
    public function testCommentExactly5001CharsFailsValidation(): void
    {
        $text = str_repeat('b', 5001);
        $result = $this->prepareAndValidate($text);

        $this->assertFalse(
            $result,
            "Comment with exactly 5001 chars should fail validation"
        );
    }

    /**
     * Property: Boundary - exactly 1 char passes validation.
     */
    public function testCommentExactly1CharPassesValidation(): void
    {
        $text = 'a';
        $result = $this->prepareAndValidate($text);

        $this->assertTrue(
            $result,
            "Comment with exactly 1 char should pass validation"
        );
    }
}
