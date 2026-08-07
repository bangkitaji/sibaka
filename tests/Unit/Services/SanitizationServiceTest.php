<?php

namespace Tests\Unit\Services;

use App\Services\SanitizationService;
use PHPUnit\Framework\TestCase;

class SanitizationServiceTest extends TestCase
{
    private SanitizationService $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new SanitizationService();
    }

    // --- Script Tag Stripping ---

    public function testSanitizeStripsScriptTags(): void
    {
        $input = '<script>alert("xss")</script>Hello';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('</script>', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('Hello', $result);
    }

    public function testSanitizeStripsScriptTagsCaseInsensitive(): void
    {
        $input = '<SCRIPT>alert("xss")</SCRIPT>Hello';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('<SCRIPT>', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('Hello', $result);
    }

    public function testSanitizeStripsScriptTagsWithAttributes(): void
    {
        $input = '<script type="text/javascript" src="evil.js"></script>Content';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('<script', $result);
        $this->assertStringNotContainsString('evil.js', $result);
        $this->assertStringContainsString('Content', $result);
    }

    public function testSanitizeStripsMultipleScriptTags(): void
    {
        $input = '<script>one</script>Middle<script>two</script>End';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('one', $result);
        $this->assertStringNotContainsString('two', $result);
        $this->assertStringContainsString('Middle', $result);
        $this->assertStringContainsString('End', $result);
    }

    // --- Event Handler Stripping ---

    public function testSanitizeStripsOnclickHandler(): void
    {
        $input = '<div onclick="alert(1)">content</div>';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('content', $result);
    }

    public function testSanitizeStripsOnmouseoverHandler(): void
    {
        $input = '<a onmouseover="steal()">link</a>';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('onmouseover', $result);
        $this->assertStringNotContainsString('steal', $result);
        $this->assertStringContainsString('link', $result);
    }

    public function testSanitizeStripsOnerrorHandler(): void
    {
        $input = '<img onerror="evil()" src="x">';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('onerror', $result);
        $this->assertStringNotContainsString('evil', $result);
    }

    public function testSanitizeStripsOnloadHandler(): void
    {
        $input = '<body onload="hack()">page</body>';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('onload', $result);
        $this->assertStringNotContainsString('hack', $result);
        $this->assertStringContainsString('page', $result);
    }

    // --- JavaScript URL Stripping ---

    public function testSanitizeStripsJavascriptUrls(): void
    {
        $input = '<a href="javascript:alert(1)">click</a>';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('javascript:', $result);
        $this->assertStringContainsString('click', $result);
    }

    public function testSanitizeStripsJavascriptUrlsCaseInsensitive(): void
    {
        $input = '<a href="JavaScript:alert(1)">click</a>';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('javascript:', strtolower($result));
        $this->assertStringContainsString('click', $result);
    }

    public function testSanitizeStripsJavascriptInSrcAttribute(): void
    {
        $input = '<img src="javascript:alert(1)">';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('javascript:', strtolower($result));
    }

    // --- Special Character Escaping ---

    public function testSanitizeEscapesLessThan(): void
    {
        $input = 'a < b';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringContainsString('&lt;', $result);
        $this->assertStringNotContainsString('<', str_replace('&lt;', '', $result));
    }

    public function testSanitizeEscapesGreaterThan(): void
    {
        $input = 'a > b';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringContainsString('&gt;', $result);
    }

    public function testSanitizeEscapesAmpersand(): void
    {
        $input = 'a & b';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringContainsString('&amp;', $result);
    }

    public function testSanitizeEscapesDoubleQuotes(): void
    {
        $input = 'say "hello"';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringContainsString('&quot;', $result);
    }

    public function testSanitizeEscapesSingleQuotes(): void
    {
        $input = "it's fine";
        $result = $this->sanitizer->sanitize($input);

        // PHP htmlspecialchars with ENT_QUOTES|ENT_HTML5 uses &apos; for single quotes
        $this->assertStringContainsString('&apos;', $result);
    }

    // --- Safe Content Preservation ---

    public function testSanitizePreservesPlainText(): void
    {
        $input = 'Hello World, this is a normal post about programming';
        $result = $this->sanitizer->sanitize($input);

        $this->assertEquals('Hello World, this is a normal post about programming', $result);
    }

    public function testSanitizePreservesNewlines(): void
    {
        $input = "Line 1\nLine 2\nLine 3";
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringContainsString("\n", $result);
    }

    public function testSanitizePreservesUnicode(): void
    {
        $input = 'こんにちは 世界 🌍';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringContainsString('こんにちは', $result);
        $this->assertStringContainsString('🌍', $result);
    }

    public function testSanitizeHandlesEmptyString(): void
    {
        $result = $this->sanitizer->sanitize('');

        $this->assertEquals('', $result);
    }

    // --- Iframe and Object Tag Stripping ---

    public function testSanitizeStripsIframeTags(): void
    {
        $input = '<iframe src="http://evil.com"></iframe>Content';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('<iframe', $result);
        $this->assertStringNotContainsString('evil.com', $result);
        $this->assertStringContainsString('Content', $result);
    }

    public function testSanitizeStripsObjectTags(): void
    {
        $input = '<object data="evil.swf"></object>Safe';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('<object', $result);
        $this->assertStringContainsString('Safe', $result);
    }

    public function testSanitizeStripsEmbedTags(): void
    {
        $input = '<embed src="evil.swf">Content';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('<embed', $result);
        $this->assertStringContainsString('Content', $result);
    }

    // --- HTML Sanitization (preserving safe tags) ---

    public function testSanitizeHtmlPreservesSafeTags(): void
    {
        $input = '<p>Hello <strong>world</strong></p>';
        $result = $this->sanitizer->sanitizeHtml($input);

        $this->assertStringContainsString('<p>', $result);
        $this->assertStringContainsString('<strong>', $result);
        $this->assertStringContainsString('Hello', $result);
    }

    public function testSanitizeHtmlStripsScriptTags(): void
    {
        $input = '<p>Hello</p><script>alert(1)</script><p>World</p>';
        $result = $this->sanitizer->sanitizeHtml($input);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('<p>Hello</p>', $result);
        $this->assertStringContainsString('<p>World</p>', $result);
    }

    public function testSanitizeHtmlStripsEventHandlersFromAllowedTags(): void
    {
        $input = '<p onclick="evil()">text</p>';
        $result = $this->sanitizer->sanitizeHtml($input);

        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringContainsString('text', $result);
    }

    public function testSanitizeHtmlStripsJavascriptUrlsFromLinks(): void
    {
        $input = '<a href="javascript:alert(1)">click</a>';
        $result = $this->sanitizer->sanitizeHtml($input);

        $this->assertStringNotContainsString('javascript:', strtolower($result));
        $this->assertStringContainsString('click', $result);
    }

    // --- containsDangerousContent Detection ---

    public function testDetectsDangerousScriptTags(): void
    {
        $this->assertTrue($this->sanitizer->containsDangerousContent('<script>alert(1)</script>'));
    }

    public function testDetectsDangerousEventHandlers(): void
    {
        $this->assertTrue($this->sanitizer->containsDangerousContent('<div onclick="evil()">'));
    }

    public function testDetectsDangerousJavascriptUrls(): void
    {
        $this->assertTrue($this->sanitizer->containsDangerousContent('javascript:alert(1)'));
    }

    public function testSafeContentNotFlaggedAsDangerous(): void
    {
        $this->assertFalse($this->sanitizer->containsDangerousContent('Hello world, normal content'));
    }

    public function testCodeExamplesNotFlaggedIfNoActualScriptTag(): void
    {
        // Plain text discussing script tags in prose (no actual tags)
        $this->assertFalse($this->sanitizer->containsDangerousContent('Use the console.log function'));
    }

    // --- File Type Validation ---

    public function testAllowedFileTypeAcceptsMd(): void
    {
        $this->assertTrue(SanitizationService::isAllowedFileType('md'));
    }

    public function testAllowedFileTypeAcceptsTxt(): void
    {
        $this->assertTrue(SanitizationService::isAllowedFileType('txt'));
    }

    public function testAllowedFileTypeAcceptsPdf(): void
    {
        $this->assertTrue(SanitizationService::isAllowedFileType('pdf'));
    }

    public function testAllowedFileTypeAcceptsPng(): void
    {
        $this->assertTrue(SanitizationService::isAllowedFileType('png'));
    }

    public function testAllowedFileTypeAcceptsJpg(): void
    {
        $this->assertTrue(SanitizationService::isAllowedFileType('jpg'));
    }

    public function testAllowedFileTypeAcceptsJpeg(): void
    {
        $this->assertTrue(SanitizationService::isAllowedFileType('jpeg'));
    }

    public function testAllowedFileTypeAcceptsGif(): void
    {
        $this->assertTrue(SanitizationService::isAllowedFileType('gif'));
    }

    public function testAllowedFileTypeRejectsExe(): void
    {
        $this->assertFalse(SanitizationService::isAllowedFileType('exe'));
    }

    public function testAllowedFileTypeRejectsPhp(): void
    {
        $this->assertFalse(SanitizationService::isAllowedFileType('php'));
    }

    public function testAllowedFileTypeRejectsJs(): void
    {
        $this->assertFalse(SanitizationService::isAllowedFileType('js'));
    }

    public function testAllowedFileTypeRejectsSvg(): void
    {
        $this->assertFalse(SanitizationService::isAllowedFileType('svg'));
    }

    public function testAllowedFileTypeRejectsHtml(): void
    {
        $this->assertFalse(SanitizationService::isAllowedFileType('html'));
    }

    public function testAllowedFileTypeIsCaseInsensitive(): void
    {
        $this->assertTrue(SanitizationService::isAllowedFileType('PNG'));
        $this->assertTrue(SanitizationService::isAllowedFileType('Jpg'));
    }

    // --- File Size and Count Constraints ---

    public function testMaxFileSizeIs10Mb(): void
    {
        $this->assertEquals(10240, SanitizationService::maxFileSizeKb());
    }

    public function testMaxFilesPerUploadIs5(): void
    {
        $this->assertEquals(5, SanitizationService::maxFilesPerUpload());
    }

    public function testAllowedFileExtensionsContainsAllRequired(): void
    {
        $extensions = SanitizationService::allowedFileExtensions();

        $this->assertContains('md', $extensions);
        $this->assertContains('txt', $extensions);
        $this->assertContains('pdf', $extensions);
        $this->assertContains('png', $extensions);
        $this->assertContains('jpg', $extensions);
        $this->assertContains('jpeg', $extensions);
        $this->assertContains('gif', $extensions);
        $this->assertCount(7, $extensions);
    }

    // --- Edge Cases ---

    public function testSanitizeHandlesNestedScriptTags(): void
    {
        $input = '<script><script>alert(1)</script></script>Safe';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('<script', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('Safe', $result);
    }

    public function testSanitizeHandlesMalformedTags(): void
    {
        $input = '<script >alert(1)</script >Content';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('Content', $result);
    }

    public function testSanitizeStripsFormTags(): void
    {
        $input = '<form action="http://evil.com"><input type="text"></form>Safe';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('<form', $result);
        $this->assertStringNotContainsString('<input', $result);
        $this->assertStringContainsString('Safe', $result);
    }

    public function testSanitizeStripsSvgTags(): void
    {
        $input = '<svg onload="evil()"><circle></circle></svg>Content';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('<svg', $result);
        $this->assertStringNotContainsString('onload', $result);
        $this->assertStringContainsString('Content', $result);
    }
}
