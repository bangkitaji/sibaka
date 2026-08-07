<?php

namespace Tests\Property;

use App\Services\SanitizationService;
use Eris\Generators;
use Eris\TestTrait;
use Tests\TestCase;

/**
 * Property 23: XSS Sanitization
 *
 * For any input string, the sanitization function SHALL produce output that
 * contains no executable script content (no <script> tags, no on* event
 * handlers, no javascript: URLs). Special characters (<, >, &, ", ') SHALL
 * be escaped.
 *
 * **Validates: Requirements 11.1**
 */
class XssSanitizationPropertyTest extends TestCase
{
    use TestTrait;

    private SanitizationService $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new SanitizationService();
    }

    /**
     * Property 23.1: sanitize() output never contains <script> tags.
     *
     * For ANY input string (including those with injected script patterns),
     * sanitize() output must never contain <script in any form.
     */
    public function testSanitizeOutputNeverContainsScriptTags(): void
    {
        $this->forAll(
            $this->xssInputGenerator()
        )
            ->then(function (string $input) {
                $output = $this->sanitizer->sanitize($input);

                $this->assertDoesNotMatchRegularExpression(
                    '/<\s*script/i',
                    $output,
                    "sanitize() output must never contain <script tags. Input: " . substr($input, 0, 200)
                );
            });
    }

    /**
     * Property 23.2: sanitize() output never contains on* event handler attributes.
     *
     * For ANY input string, sanitize() output must never contain event handlers
     * like onclick, onload, onerror, onmouseover, etc.
     */
    public function testSanitizeOutputNeverContainsEventHandlers(): void
    {
        $this->forAll(
            $this->xssInputGenerator()
        )
            ->then(function (string $input) {
                $output = $this->sanitizer->sanitize($input);

                // After full sanitize (which strips tags and escapes), no on* handlers should remain
                // The pattern matches on[word]= which is the dangerous event handler format
                $this->assertDoesNotMatchRegularExpression(
                    '/\bon\w+\s*=/i',
                    $output,
                    "sanitize() output must never contain on* event handlers. Input: " . substr($input, 0, 200)
                );
            });
    }

    /**
     * Property 23.3: sanitize() output never contains javascript: URLs in executable context.
     *
     * For ANY input string, sanitize() output must never contain javascript: protocol URLs
     * in an executable context. Since sanitize() strips all tags and escapes chars,
     * the text "javascript:" as plain content is not executable (no attribute context exists).
     */
    public function testSanitizeOutputNeverContainsJavascriptUrlsInContext(): void
    {
        $this->forAll(
            $this->xssInputGenerator()
        )
            ->then(function (string $input) {
                $output = $this->sanitizer->sanitize($input);

                // After sanitize(), all tags are stripped. So there can be no href="javascript:..." context.
                // Verify no HTML tags remain (which implicitly prevents javascript: in attributes)
                $this->assertDoesNotMatchRegularExpression(
                    '/<[a-z]/i',
                    $output,
                    "sanitize() output must not contain any HTML tags. Input: " . substr($input, 0, 200)
                );

                // Also explicitly verify no attribute-context javascript: URLs
                $this->assertDoesNotMatchRegularExpression(
                    '/(?:href|src|action)\s*=\s*["\']?\s*javascript\s*:/i',
                    $output,
                    "sanitize() output must never contain javascript: in attribute context. Input: " . substr($input, 0, 200)
                );
            });
    }

    /**
     * Property 23.4: escapeSpecialChars() escapes all dangerous characters.
     *
     * For ANY input string, escapeSpecialChars() output must have all
     * <, >, &, ", ' characters escaped to their HTML entity equivalents.
     */
    public function testEscapeSpecialCharsEscapesAllDangerousCharacters(): void
    {
        $this->forAll(
            Generators::string()
        )
            ->then(function (string $input) {
                $output = $this->sanitizer->escapeSpecialChars($input);

                // After escaping, raw <, >, ", ' should not appear
                // & is tricky because entities contain & - so we check that
                // no unescaped & exists (& not followed by entity pattern is hard to check,
                // so we verify the specific dangerous chars are escaped)
                $this->assertStringNotContainsString('<', $output,
                    "escapeSpecialChars() must escape < character");
                $this->assertStringNotContainsString('>', $output,
                    "escapeSpecialChars() must escape > character");
                $this->assertStringNotContainsString('"', $output,
                    "escapeSpecialChars() must escape \" character");
                $this->assertStringNotContainsString("'", $output,
                    "escapeSpecialChars() must escape ' character");

                // Verify & is properly escaped (any raw & should be part of an entity)
                // htmlspecialchars converts & to &amp; so no raw & should be left
                // that isn't part of an entity reference
                $withoutEntities = preg_replace('/&(?:amp|lt|gt|quot|#039|#x27|apos);/', '', $output);
                $this->assertStringNotContainsString('&', $withoutEntities ?? '',
                    "escapeSpecialChars() must escape all & characters to &amp;");
            });
    }

    /**
     * Property 23.5: sanitizeHtml() preserves safe tags but strips dangerous elements.
     *
     * For ANY input containing safe HTML tags (<p>, <strong>, <em>),
     * sanitizeHtml() must preserve them. But <script>, <iframe>, on* attributes
     * must always be stripped.
     */
    public function testSanitizeHtmlPreservesSafeTagsStripsUnsafe(): void
    {
        $this->forAll(
            $this->htmlContentGenerator()
        )
            ->then(function (string $input) {
                $output = $this->sanitizer->sanitizeHtml($input);

                // Dangerous tags must never appear in output
                $this->assertDoesNotMatchRegularExpression(
                    '/<\s*script/i',
                    $output,
                    "sanitizeHtml() must strip <script> tags"
                );
                $this->assertDoesNotMatchRegularExpression(
                    '/<\s*iframe/i',
                    $output,
                    "sanitizeHtml() must strip <iframe> tags"
                );

                // on* event handlers must be stripped
                $this->assertDoesNotMatchRegularExpression(
                    '/\bon\w+\s*=/i',
                    $output,
                    "sanitizeHtml() must strip on* event handlers"
                );

                // javascript: URLs must be removed
                $this->assertDoesNotMatchRegularExpression(
                    '/javascript\s*:/i',
                    $output,
                    "sanitizeHtml() must strip javascript: URLs"
                );
            });
    }

    /**
     * Property 23.6: sanitizeHtml() preserves safe formatting tags.
     *
     * When input contains only safe tags, they should be preserved in output.
     */
    public function testSanitizeHtmlPreservesSafeFormattingTags(): void
    {
        $safeInputs = [
            '<p>Hello world</p>',
            '<strong>Bold text</strong>',
            '<em>Italic text</em>',
            '<p><strong>Nested</strong> <em>tags</em></p>',
            '<ul><li>Item 1</li><li>Item 2</li></ul>',
            '<blockquote>A quote</blockquote>',
            '<code>console.log("test")</code>',
            '<pre><code>function foo() {}</code></pre>',
            '<h1>Title</h1><h2>Subtitle</h2>',
        ];

        foreach ($safeInputs as $input) {
            $output = $this->sanitizer->sanitizeHtml($input);

            $this->assertEquals(
                $input,
                $output,
                "sanitizeHtml() must preserve safe HTML: {$input}"
            );
        }
    }

    /**
     * Property 23.7: File extension validation accepts only allowed types.
     *
     * For ANY file extension, isAllowedFileType() returns true iff extension
     * is one of: md, txt, pdf, png, jpg, jpeg, gif.
     */
    public function testFileExtensionValidation(): void
    {
        $allowedExtensions = ['md', 'txt', 'pdf', 'png', 'jpg', 'jpeg', 'gif'];
        $dangerousExtensions = ['exe', 'bat', 'cmd', 'sh', 'php', 'js', 'html', 'htm',
            'svg', 'xml', 'asp', 'aspx', 'jsp', 'py', 'rb', 'pl', 'cgi', 'com',
            'vbs', 'ps1', 'msi', 'scr', 'pif', 'hta', 'jar'];

        // Allowed extensions must be accepted
        foreach ($allowedExtensions as $ext) {
            $this->assertTrue(
                SanitizationService::isAllowedFileType($ext),
                "Extension '{$ext}' should be allowed"
            );
            // Test case insensitivity
            $this->assertTrue(
                SanitizationService::isAllowedFileType(strtoupper($ext)),
                "Extension '" . strtoupper($ext) . "' should be allowed (case insensitive)"
            );
        }

        // Dangerous extensions must be rejected
        foreach ($dangerousExtensions as $ext) {
            $this->assertFalse(
                SanitizationService::isAllowedFileType($ext),
                "Extension '{$ext}' should be rejected"
            );
        }
    }

    /**
     * Generator for XSS attack input strings.
     * Produces a mix of benign and malicious input patterns.
     */
    private function xssInputGenerator(): \Eris\Generator
    {
        return Generators::oneOf(
            // Regular strings
            Generators::string(),
            // Strings with script tags
            Generators::map(
                function (string $payload) {
                    return '<script>' . $payload . '</script>';
                },
                Generators::string()
            ),
            // Strings with event handlers
            Generators::map(
                function (string $payload) {
                    $handlers = ['onclick', 'onload', 'onerror', 'onmouseover', 'onfocus', 'onblur'];
                    $handler = $handlers[array_rand($handlers)];
                    return '<div ' . $handler . '="' . $payload . '">test</div>';
                },
                Generators::string()
            ),
            // Strings with javascript: URLs
            Generators::map(
                function (string $payload) {
                    return '<a href="javascript:' . $payload . '">click</a>';
                },
                Generators::string()
            ),
            // Mixed XSS payloads from common attack vectors
            Generators::elements([
                '<script>alert("XSS")</script>',
                '<ScRiPt>alert("XSS")</ScRiPt>',
                '<script src="http://evil.com/xss.js"></script>',
                '<img src=x onerror=alert(1)>',
                '<svg onload=alert(1)>',
                '<body onload=alert(1)>',
                '<iframe src="javascript:alert(1)">',
                '<a href="javascript:void(0)" onclick="alert(1)">link</a>',
                '"><script>alert(document.cookie)</script>',
                "';alert(String.fromCharCode(88,83,83))//",
                '<IMG SRC="javascript:alert(\'XSS\');">',
                '<IMG SRC=javascript:alert(&quot;XSS&quot;)>',
                '<IMG SRC=`javascript:alert("XSS")`>',
                '<div style="background-image:url(javascript:alert(\'XSS\'))">',
                '<input type="text" value="" onfocus="alert(1)" autofocus>',
                '<marquee onstart=alert(1)>',
                '<video><source onerror="javascript:alert(1)">',
                '<math><mtext><table><mglyph><svg><mtext><textarea><path onload=alert(1)>',
                "\"><img src=x onerror=alert(1)>",
                "javascript:/*--></title></style></textarea></script></xmp><svg/onload='+/\"/+/onmouseover=1/+/[*/[]/+alert(1)//'>",
            ]),
            // Obfuscated script patterns
            Generators::elements([
                '<scr<script>ipt>alert(1)</scr</script>ipt>',
                '<script/xss>alert(1)</script>',
                '<<script>alert("XSS");//<</script>',
                '<script\x20type="text/javascript">alert(1)</script>',
                '<script\x0dtype="text/javascript">alert(1)</script>',
                '<img src="x" onerror="&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;&#58;alert(1)">',
                '<a href="&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;&#58;alert(1)">XSS</a>',
            ])
        );
    }

    /**
     * Generator for HTML content that mixes safe and unsafe elements.
     */
    private function htmlContentGenerator(): \Eris\Generator
    {
        return Generators::oneOf(
            // Safe HTML with injected script tags
            Generators::map(
                function (string $text) {
                    return '<p>' . $text . '</p><script>alert("xss")</script><p>safe</p>';
                },
                Generators::string()
            ),
            // Safe HTML with event handlers injected
            Generators::map(
                function (string $text) {
                    return '<p onclick="alert(1)">' . $text . '</p>';
                },
                Generators::string()
            ),
            // Safe HTML with javascript: URLs
            Generators::map(
                function (string $text) {
                    return '<p>' . $text . '</p><a href="javascript:alert(1)">evil</a>';
                },
                Generators::string()
            ),
            // Mixed safe and unsafe
            Generators::elements([
                '<p>Hello</p><script>evil()</script><strong>World</strong>',
                '<em>Text</em><iframe src="http://evil.com"></iframe>',
                '<p onmouseover="alert(1)">Hover me</p>',
                '<strong>Bold</strong><img src=x onerror=alert(1)>',
                '<blockquote>Quote</blockquote><svg onload=alert(1)></svg>',
                '<h1>Title</h1><link rel="stylesheet" href="javascript:alert(1)">',
                '<ul><li>Item</li></ul><object data="javascript:alert(1)"></object>',
                '<code>safe code</code><embed src="javascript:alert(1)">',
            ])
        );
    }
}
