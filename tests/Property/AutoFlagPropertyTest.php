<?php

namespace Tests\Property;

use App\Services\ModerationService;
use Eris\Generators;
use Eris\TestTrait;
use Tests\TestCase;

/**
 * Property 29: Auto-Flagging Pattern Detection
 *
 * For any content text, the auto-flag system SHALL flag it if and only if it
 * contains at least one match against the predefined pattern list (swear words,
 * spam patterns, malicious links). Content without any pattern matches SHALL
 * not be auto-flagged.
 *
 * Properties tested:
 * 1. checkAutoFlag returns non-empty array iff content contains at least 1 pattern match
 * 2. Clean content (no patterns) returns empty array
 * 3. Content with spam phrases is detected in 'spam' category
 * 4. Content with malicious links is detected in 'malicious_links' category
 * 5. Content with inappropriate words is detected in 'inappropriate' category
 *
 * **Validates: Requirements 12.6**
 */
class AutoFlagPropertyTest extends TestCase
{
    use TestTrait;

    private ModerationService $moderationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->moderationService = app(ModerationService::class);
    }

    /**
     * Property 29.1: Clean content (no pattern matches) returns empty array.
     *
     * For ANY content string that does NOT contain any known spam phrases, malicious
     * link patterns, or inappropriate words, checkAutoFlag() SHALL return an empty array.
     */
    public function testCleanContentReturnsEmptyArray(): void
    {
        $this->forAll(
            $this->cleanContentGenerator()
        )
            ->then(function (string $content) {
                $result = $this->moderationService->checkAutoFlag($content);

                $this->assertIsArray($result);
                $this->assertEmpty(
                    $result,
                    "Clean content should not be flagged. Content: '" . substr($content, 0, 100) . "', Got categories: " . implode(', ', $result)
                );
            });
    }

    /**
     * Property 29.2: Content with spam phrases is always flagged with 'spam' category.
     *
     * For ANY content that contains at least one spam phrase (buy now, click here,
     * free money, act now, limited time, winner, congratulations you won) or a
     * shortened URL, checkAutoFlag() SHALL include 'spam' in the result.
     */
    public function testContentWithSpamPhrasesIsFlagged(): void
    {
        $this->forAll(
            $this->spamContentGenerator()
        )
            ->then(function (string $content) {
                $result = $this->moderationService->checkAutoFlag($content);

                $this->assertIsArray($result);
                $this->assertNotEmpty(
                    $result,
                    "Content with spam patterns must be flagged. Content: '" . substr($content, 0, 100) . "'"
                );
                $this->assertContains(
                    'spam',
                    $result,
                    "Content with spam patterns must be flagged in 'spam' category. Content: '" . substr($content, 0, 100) . "'"
                );
            });
    }

    /**
     * Property 29.3: Content with malicious links is always flagged with 'malicious_links' category.
     *
     * For ANY content that contains .exe/.bat/.cmd/.scr/.pif/.vbs/.js URLs,
     * javascript: protocol, or data:text/html, checkAutoFlag() SHALL include
     * 'malicious_links' in the result.
     */
    public function testContentWithMaliciousLinksIsFlagged(): void
    {
        $this->forAll(
            $this->maliciousLinkContentGenerator()
        )
            ->then(function (string $content) {
                $result = $this->moderationService->checkAutoFlag($content);

                $this->assertIsArray($result);
                $this->assertNotEmpty(
                    $result,
                    "Content with malicious links must be flagged. Content: '" . substr($content, 0, 100) . "'"
                );
                $this->assertContains(
                    'malicious_links',
                    $result,
                    "Content with malicious links must be flagged in 'malicious_links' category. Content: '" . substr($content, 0, 100) . "'"
                );
            });
    }

    /**
     * Property 29.4: Content with inappropriate words is always flagged with 'inappropriate' category.
     *
     * For ANY content that contains words like "spam", "phishing", or "scam",
     * checkAutoFlag() SHALL include 'inappropriate' in the result.
     */
    public function testContentWithInappropriateWordsIsFlagged(): void
    {
        $this->forAll(
            $this->inappropriateContentGenerator()
        )
            ->then(function (string $content) {
                $result = $this->moderationService->checkAutoFlag($content);

                $this->assertIsArray($result);
                $this->assertNotEmpty(
                    $result,
                    "Content with inappropriate words must be flagged. Content: '" . substr($content, 0, 100) . "'"
                );
                $this->assertContains(
                    'inappropriate',
                    $result,
                    "Content with inappropriate words must be flagged in 'inappropriate' category. Content: '" . substr($content, 0, 100) . "'"
                );
            });
    }

    /**
     * Property 29.5: checkAutoFlag returns non-empty iff content has at least 1 pattern match.
     *
     * This is the biconditional property: content is flagged iff it matches patterns.
     * Generate random content that may or may not contain patterns, and verify the
     * flagging result matches the expectation.
     */
    public function testFlaggingBiconditional(): void
    {
        $this->forAll(
            Generators::oneOf(
                $this->cleanContentGenerator(),
                $this->spamContentGenerator(),
                $this->maliciousLinkContentGenerator(),
                $this->inappropriateContentGenerator()
            )
        )
            ->then(function (string $content) {
                $result = $this->moderationService->checkAutoFlag($content);
                $isFlagged = !empty($result);

                // Check if content actually contains any pattern match
                $containsPattern = $this->contentContainsAnyPattern($content);

                $this->assertEquals(
                    $containsPattern,
                    $isFlagged,
                    $isFlagged
                        ? "Content was flagged but should not have been. Content: '" . substr($content, 0, 100) . "', Categories: " . implode(', ', $result)
                        : "Content was NOT flagged but should have been. Content: '" . substr($content, 0, 100) . "'"
                );
            });
    }

    /**
     * Property 29.6: Each returned category corresponds to an actual pattern match in that category.
     *
     * For ANY content, every category in checkAutoFlag result must have at least one
     * matching pattern in that category's pattern set.
     */
    public function testReturnedCategoriesCorrespondToActualMatches(): void
    {
        $this->forAll(
            Generators::oneOf(
                $this->spamContentGenerator(),
                $this->maliciousLinkContentGenerator(),
                $this->inappropriateContentGenerator(),
                $this->multiCategoryContentGenerator()
            )
        )
            ->then(function (string $content) {
                $result = $this->moderationService->checkAutoFlag($content);

                foreach ($result as $category) {
                    $this->assertTrue(
                        $this->contentMatchesCategoryPattern($content, $category),
                        "Returned category '{$category}' must have a corresponding pattern match in content: '" . substr($content, 0, 100) . "'"
                    );
                }
            });
    }

    /**
     * Check if content contains any pattern from any category.
     */
    private function contentContainsAnyPattern(string $content): bool
    {
        $allPatterns = [
            '/\b(buy now|click here|free money|act now|limited time|winner|congratulations you won)\b/i',
            '/https?:\/\/(?:bit\.ly|tinyurl\.com|t\.co)\/[a-zA-Z0-9]+/i',
            '/https?:\/\/[^\s]*\.(exe|bat|cmd|scr|pif|vbs|js)(\s|$)/i',
            '/javascript:/i',
            '/data:text\/html/i',
            '/\b(spam|phishing|scam)\b/i',
        ];

        foreach ($allPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if content matches patterns for a specific category.
     */
    private function contentMatchesCategoryPattern(string $content, string $category): bool
    {
        $categoryPatterns = [
            'spam' => [
                '/\b(buy now|click here|free money|act now|limited time|winner|congratulations you won)\b/i',
                '/https?:\/\/(?:bit\.ly|tinyurl\.com|t\.co)\/[a-zA-Z0-9]+/i',
            ],
            'malicious_links' => [
                '/https?:\/\/[^\s]*\.(exe|bat|cmd|scr|pif|vbs|js)(\s|$)/i',
                '/javascript:/i',
                '/data:text\/html/i',
            ],
            'inappropriate' => [
                '/\b(spam|phishing|scam)\b/i',
            ],
        ];

        if (!isset($categoryPatterns[$category])) {
            return false;
        }

        foreach ($categoryPatterns[$category] as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generator for clean content that should NOT trigger any auto-flag patterns.
     * Produces safe technical discussion text without any flagged keywords.
     */
    private function cleanContentGenerator(): \Eris\Generator
    {
        return Generators::oneOf(
            // Safe technical strings
            Generators::elements([
                'This is a technical discussion about Laravel.',
                'How to implement dependency injection in PHP 8.3',
                'React component lifecycle methods explained',
                'PostgreSQL query optimization tips for large datasets',
                'Setting up CI/CD pipelines with GitHub Actions',
                'Understanding the SOLID principles in object-oriented design',
                'Tips for effective code review',
                'Building REST APIs with proper error handling',
                'Implementing caching strategies for high-traffic applications',
                'Introduction to container orchestration with Docker',
                'Best practices for database migration management',
                'Event-driven architecture patterns in microservices',
                'TypeScript generics and utility types deep dive',
                'Performance benchmarking tools for web applications',
                'Secure authentication with OAuth 2.0 and PKCE',
                'Unit testing strategies for legacy codebases',
                'Understanding WebSocket connections and real-time data',
                'GraphQL vs REST: choosing the right API paradigm',
                'Memory management in Go and garbage collection',
                'Functional programming concepts in modern JavaScript',
            ]),
            // Generated safe alphanumeric strings
            Generators::map(
                function (string $text) {
                    // Strip any characters that might accidentally form patterns
                    $safe = preg_replace('/[^a-zA-Z0-9\s.,!?\-]/', '', $text);
                    // Ensure no pattern words remain
                    $safe = preg_replace('/\b(buy now|click here|free money|act now|limited time|winner|congratulations you won|spam|phishing|scam)\b/i', 'safe', $safe ?? '');
                    return !empty(trim($safe ?? '')) ? trim($safe) : 'A safe technical article about programming.';
                },
                Generators::string()
            ),
            // Safe sentences with technical terms
            Generators::map(
                function (int $seed) {
                    $subjects = ['The developer', 'Our team', 'This module', 'The function', 'The algorithm'];
                    $verbs = ['implements', 'optimizes', 'refactors', 'designs', 'tests'];
                    $objects = ['the database layer', 'the caching system', 'the API endpoint', 'the user interface', 'the build pipeline'];

                    $subject = $subjects[$seed % count($subjects)];
                    $verb = $verbs[($seed * 3) % count($verbs)];
                    $object = $objects[($seed * 7) % count($objects)];

                    return "{$subject} {$verb} {$object} for better performance.";
                },
                Generators::choose(1, 10000)
            )
        );
    }

    /**
     * Generator for content containing spam phrases.
     */
    private function spamContentGenerator(): \Eris\Generator
    {
        return Generators::oneOf(
            // Direct spam phrases embedded in text
            Generators::map(
                function (int $seed) {
                    $spamPhrases = ['buy now', 'click here', 'free money', 'act now', 'limited time', 'winner', 'congratulations you won'];
                    $prefixes = ['Check this out: ', 'You should ', 'Hey everyone, ', 'Important: ', 'Don\'t miss this - '];
                    $suffixes = [' and get started today.', ' for amazing results.', ' before it\'s too late.', ' right now.', '!'];

                    $phrase = $spamPhrases[$seed % count($spamPhrases)];
                    $prefix = $prefixes[($seed * 3) % count($prefixes)];
                    $suffix = $suffixes[($seed * 7) % count($suffixes)];

                    return $prefix . $phrase . $suffix;
                },
                Generators::choose(1, 10000)
            ),
            // Shortened URLs (spam-like)
            Generators::map(
                function (int $seed) {
                    $domains = ['bit.ly', 'tinyurl.com', 't.co'];
                    $domain = $domains[$seed % count($domains)];
                    $slug = substr(md5((string) $seed), 0, 7);
                    $contexts = [
                        "Visit https://{$domain}/{$slug} for details.",
                        "More info: http://{$domain}/{$slug}",
                        "Check out https://{$domain}/{$slug} now!",
                    ];

                    return $contexts[($seed * 5) % count($contexts)];
                },
                Generators::choose(1, 10000)
            ),
            // Spam phrases mixed with normal content
            Generators::map(
                function (int $seed) {
                    $spamPhrases = ['buy now', 'click here', 'free money', 'act now', 'limited time', 'winner', 'congratulations you won'];
                    $phrase = $spamPhrases[$seed % count($spamPhrases)];

                    return "This is a regular technical article about programming. However, you should {$phrase} to learn more about this topic.";
                },
                Generators::choose(1, 10000)
            )
        );
    }

    /**
     * Generator for content containing malicious links.
     */
    private function maliciousLinkContentGenerator(): \Eris\Generator
    {
        return Generators::oneOf(
            // .exe and other dangerous file URLs
            Generators::map(
                function (int $seed) {
                    $extensions = ['exe', 'bat', 'cmd', 'scr', 'pif', 'vbs', 'js'];
                    $ext = $extensions[$seed % count($extensions)];
                    $domains = ['example.com', 'download.net', 'files.org'];
                    $domain = $domains[($seed * 3) % count($domains)];

                    $contexts = [
                        "Download the tool from http://{$domain}/tool.{$ext} ",
                        "Get the update at https://{$domain}/update.{$ext} ",
                        "Install from http://{$domain}/setup.{$ext}\n",
                    ];

                    return $contexts[($seed * 7) % count($contexts)];
                },
                Generators::choose(1, 10000)
            ),
            // javascript: protocol
            Generators::map(
                function (int $seed) {
                    $payloads = [
                        'javascript:alert(1)',
                        'javascript:void(0)',
                        'javascript:document.cookie',
                        'javascript:eval("malicious")',
                    ];
                    $prefixes = ['Check this: ', 'Link: ', 'See ', 'Open '];

                    $payload = $payloads[$seed % count($payloads)];
                    $prefix = $prefixes[($seed * 3) % count($prefixes)];

                    return $prefix . $payload;
                },
                Generators::choose(1, 10000)
            ),
            // data:text/html
            Generators::map(
                function (int $seed) {
                    $contexts = [
                        'Try this: data:text/html,<h1>Hello</h1>',
                        'Embedded content: data:text/html,<script>alert(1)</script>',
                        'Open data:text/html,<iframe src="evil.com"></iframe>',
                    ];

                    return $contexts[$seed % count($contexts)];
                },
                Generators::choose(1, 10000)
            )
        );
    }

    /**
     * Generator for content containing inappropriate words.
     */
    private function inappropriateContentGenerator(): \Eris\Generator
    {
        return Generators::map(
            function (int $seed) {
                $words = ['spam', 'phishing', 'scam'];
                $word = $words[$seed % count($words)];

                $templates = [
                    "This content is clearly {$word} and should be removed.",
                    "Warning: detected {$word} activity in this post.",
                    "I think this is a {$word} attempt targeting new members.",
                    "Beware of {$word} messages like this one.",
                    "Report this {$word} immediately.",
                ];

                return $templates[($seed * 3) % count($templates)];
            },
            Generators::choose(1, 10000)
        );
    }

    /**
     * Generator for content containing patterns from multiple categories.
     */
    private function multiCategoryContentGenerator(): \Eris\Generator
    {
        return Generators::map(
            function (int $seed) {
                $combined = [
                    'Buy now and visit http://evil.com/malware.exe to download. This is a scam!',
                    'Click here for javascript:alert(1) - this is spam content.',
                    'Free money at https://bit.ly/abc123 - also check data:text/html for phishing.',
                    'Act now! Download from http://bad.com/tool.bat and avoid this scam.',
                    'Limited time offer with javascript:void(0) links - clearly phishing.',
                ];

                return $combined[$seed % count($combined)];
            },
            Generators::choose(1, 10000)
        );
    }
}
