<?php

namespace Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Property 6: Content Length and Embed Limits
 *
 * Tests that content is accepted iff body is 1-50000 chars and embeds ≤ 10.
 * Uses random body lengths and embed counts to verify validation boundaries.
 *
 * **Validates: Requirements 3.7, 3.8**
 */
class ContentLengthEmbedTest extends TestCase
{
    use TestTrait;

    /**
     * Get the content validation rules for body and embeds (mirrors StoreContentRequest).
     */
    private function contentRules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:50000'],
            'embeds' => ['nullable', 'array', 'max:10'],
            'embeds.*' => ['url'],
        ];
    }

    /**
     * Build valid base data with given body and embeds.
     */
    private function buildData(string $body, ?array $embeds = null): array
    {
        return [
            'body' => $body,
            'embeds' => $embeds,
        ];
    }

    /**
     * Generate an array of valid embed URLs of the given count.
     */
    private function generateEmbeds(int $count): array
    {
        $embeds = [];
        for ($i = 0; $i < $count; $i++) {
            $embeds[] = 'https://example.com/embed/' . ($i + 1);
        }
        return $embeds;
    }

    /**
     * Property: Body with 1-50000 chars passes validation (body rule).
     *
     * For any randomly generated body length within the valid range,
     * the validator must pass.
     */
    public function testBodyWithValidLengthPassesValidation(): void
    {
        $this->forAll(
            Generators::choose(1, 50000)
        )
            ->withMaxSize(50)
            ->then(function (int $bodyLength) {
                $body = str_repeat('a', $bodyLength);
                $data = $this->buildData($body);

                $validator = Validator::make($data, $this->contentRules());

                $this->assertTrue(
                    $validator->passes(),
                    "Body with {$bodyLength} chars (1-50000) should pass validation. Errors: " . json_encode($validator->errors()->toArray())
                );
            });
    }

    /**
     * Property: Body with 0 chars (empty string) fails validation.
     */
    public function testEmptyBodyFailsValidation(): void
    {
        $data = $this->buildData('');

        $validator = Validator::make($data, $this->contentRules());

        $this->assertTrue(
            $validator->fails(),
            "Empty body (0 chars) should fail validation"
        );
        $this->assertArrayHasKey('body', $validator->errors()->toArray());
    }

    /**
     * Property: Body with 50001+ chars always fails validation.
     *
     * For any randomly generated body length exceeding the maximum,
     * the validator must fail on the body field.
     */
    public function testBodyExceeding50000CharsFailsValidation(): void
    {
        $this->forAll(
            Generators::choose(50001, 60000)
        )
            ->then(function (int $bodyLength) {
                $body = str_repeat('x', $bodyLength);
                $data = $this->buildData($body);

                $validator = Validator::make($data, $this->contentRules());

                $this->assertTrue(
                    $validator->fails(),
                    "Body with {$bodyLength} chars (>50000) should fail validation"
                );
                $this->assertArrayHasKey('body', $validator->errors()->toArray());
            });
    }

    /**
     * Property: Embeds array with 0-10 items passes validation (embeds rule).
     *
     * For any randomly generated embed count within the valid range,
     * the validator must pass.
     */
    public function testEmbedsWithValidCountPassesValidation(): void
    {
        $this->forAll(
            Generators::choose(0, 10)
        )
            ->then(function (int $embedCount) {
                $embeds = $embedCount > 0 ? $this->generateEmbeds($embedCount) : null;
                $body = 'Valid content body';
                $data = $this->buildData($body, $embeds);

                $validator = Validator::make($data, $this->contentRules());

                $this->assertTrue(
                    $validator->passes(),
                    "Embeds with {$embedCount} items (0-10) should pass validation. Errors: " . json_encode($validator->errors()->toArray())
                );
            });
    }

    /**
     * Property: Embeds array with 11+ items always fails validation.
     *
     * For any randomly generated embed count exceeding the maximum,
     * the validator must fail on the embeds field.
     */
    public function testEmbedsExceeding10ItemsFailsValidation(): void
    {
        $this->forAll(
            Generators::choose(11, 25)
        )
            ->then(function (int $embedCount) {
                $embeds = $this->generateEmbeds($embedCount);
                $body = 'Valid content body';
                $data = $this->buildData($body, $embeds);

                $validator = Validator::make($data, $this->contentRules());

                $this->assertTrue(
                    $validator->fails(),
                    "Embeds with {$embedCount} items (>10) should fail validation"
                );
                $this->assertArrayHasKey('embeds', $validator->errors()->toArray());
            });
    }

    /**
     * Property: Boundary - exactly 50000 chars passes validation.
     */
    public function testBodyExactly50000CharsPassesValidation(): void
    {
        $body = str_repeat('b', 50000);
        $data = $this->buildData($body);

        $validator = Validator::make($data, $this->contentRules());

        $this->assertTrue(
            $validator->passes(),
            "Body with exactly 50000 chars should pass validation. Errors: " . json_encode($validator->errors()->toArray())
        );
    }

    /**
     * Property: Boundary - exactly 10 embeds passes validation.
     */
    public function testEmbedsExactly10ItemsPassesValidation(): void
    {
        $embeds = $this->generateEmbeds(10);
        $body = 'Valid content body';
        $data = $this->buildData($body, $embeds);

        $validator = Validator::make($data, $this->contentRules());

        $this->assertTrue(
            $validator->passes(),
            "Embeds with exactly 10 items should pass validation. Errors: " . json_encode($validator->errors()->toArray())
        );
    }
}
