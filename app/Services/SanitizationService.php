<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Service responsible for sanitizing user-generated content to prevent XSS attacks.
 *
 * Strips dangerous HTML tags, event handlers, and javascript: URLs.
 * Escapes special characters (<, >, &, ", ') in plain text content.
 */
class SanitizationService
{
    /**
     * Dangerous HTML tags that must always be removed.
     */
    private const DANGEROUS_TAGS = [
        'script',
        'iframe',
        'object',
        'embed',
        'applet',
        'form',
        'input',
        'button',
        'select',
        'textarea',
        'link',
        'meta',
        'base',
        'svg',
        'math',
    ];

    /**
     * Pattern to match on* event handler attributes.
     */
    private const EVENT_HANDLER_PATTERN = '/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]*)/i';

    /**
     * Pattern to match javascript: URLs in href/src/action attributes.
     */
    private const JAVASCRIPT_URL_PATTERN = '/(?:href|src|action|formaction|data|poster|background)\s*=\s*(?:"[^"]*javascript:[^"]*"|\'[^\']*javascript:[^\']*\'|[^\s>]*javascript:[^\s>]*)/i';

    /**
     * Sanitize content by stripping dangerous HTML and escaping special characters.
     *
     * This is the primary method for sanitizing user-generated content (titles, bodies, comments).
     * It strips all HTML tags and escapes special characters for safe output.
     */
    public function sanitize(string $input): string
    {
        // First, remove dangerous tags and their content
        $output = $this->stripDangerousTags($input);

        // Remove on* event handler attributes
        $output = $this->stripEventHandlers($output);

        // Remove javascript: URLs
        $output = $this->stripJavascriptUrls($output);

        // Strip all remaining HTML tags
        $output = strip_tags($output);

        // Escape special characters
        $output = $this->escapeSpecialChars($output);

        return $output;
    }

    /**
     * Sanitize content preserving safe HTML structure (for rich text editor output).
     *
     * Strips only dangerous elements while preserving safe formatting tags.
     * Used for body_html field from TipTap editor output.
     */
    public function sanitizeHtml(string $input): string
    {
        // Remove dangerous tags and their content
        $output = $this->stripDangerousTags($input);

        // Remove on* event handler attributes
        $output = $this->stripEventHandlers($output);

        // Remove javascript: URLs
        $output = $this->stripJavascriptUrls($output);

        // Allow only safe HTML tags from TipTap output
        $allowedTags = '<p><br><strong><em><u><s><code><pre><blockquote><h1><h2><h3><h4><h5><h6><ul><ol><li><a><img><span><div><table><thead><tbody><tr><th><td><hr><sub><sup>';
        $output = strip_tags($output, $allowedTags);

        // Clean remaining attributes on allowed tags - remove any dangerous ones
        $output = $this->cleanAttributes($output);

        return $output;
    }

    /**
     * Escape special characters for safe text output.
     * Characters escaped: <, >, &, ", '
     */
    public function escapeSpecialChars(string $input): string
    {
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8', true);
    }

    /**
     * Strip dangerous HTML tags and their entire content.
     */
    public function stripDangerousTags(string $input): string
    {
        $output = $input;

        foreach (self::DANGEROUS_TAGS as $tag) {
            // Remove opening and closing tags with content between them
            // Handles whitespace variations like <script > and </script >
            $output = preg_replace(
                '/<\s*' . $tag . '\b[^>]*>.*?<\s*\/\s*' . $tag . '\s*>/is',
                '',
                $output
            ) ?? $output;

            // Remove self-closing or unclosed dangerous tags
            $output = preg_replace(
                '/<\s*' . $tag . '\b[^>]*\/?>/i',
                '',
                $output
            ) ?? $output;
        }

        return $output;
    }

    /**
     * Remove on* event handler attributes from HTML.
     */
    public function stripEventHandlers(string $input): string
    {
        return preg_replace(self::EVENT_HANDLER_PATTERN, '', $input) ?? $input;
    }

    /**
     * Remove javascript: URLs from href, src, action attributes.
     */
    public function stripJavascriptUrls(string $input): string
    {
        return preg_replace(self::JAVASCRIPT_URL_PATTERN, '', $input) ?? $input;
    }

    /**
     * Clean attributes on allowed HTML tags, removing dangerous ones.
     */
    private function cleanAttributes(string $input): string
    {
        // Remove any remaining on* event handlers
        $output = preg_replace(self::EVENT_HANDLER_PATTERN, '', $input) ?? $input;

        // Remove any remaining javascript: in attribute values
        $output = preg_replace(
            '/(?<=\s)(href|src|action)\s*=\s*["\']?\s*javascript:/i',
            '$1="',
            $output
        ) ?? $output;

        // Remove style attributes that could contain expressions
        $output = preg_replace('/\s+style\s*=\s*(?:"[^"]*expression[^"]*"|\'[^\']*expression[^\']*\')/i', '', $output) ?? $output;

        return $output;
    }

    /**
     * Check if a string contains any potentially dangerous content.
     * Useful for validation without modification.
     */
    public function containsDangerousContent(string $input): bool
    {
        // Check for script tags
        if (preg_match('/<script\b/i', $input)) {
            return true;
        }

        // Check for on* event handlers
        if (preg_match('/\bon\w+\s*=/i', $input)) {
            return true;
        }

        // Check for javascript: URLs
        if (preg_match('/javascript\s*:/i', $input)) {
            return true;
        }

        return false;
    }

    /**
     * Validate that allowed file types are submitted.
     *
     * @param string $mimeType The MIME type of the uploaded file
     * @param string $extension The file extension
     * @return bool Whether the file type is allowed
     */
    public static function isAllowedFileType(string $extension): bool
    {
        $allowedExtensions = ['md', 'txt', 'pdf', 'png', 'jpg', 'jpeg', 'gif'];

        return in_array(strtolower($extension), $allowedExtensions, true);
    }

    /**
     * Get the list of allowed file extensions.
     *
     * @return array<string>
     */
    public static function allowedFileExtensions(): array
    {
        return ['md', 'txt', 'pdf', 'png', 'jpg', 'jpeg', 'gif'];
    }

    /**
     * Get the maximum file size in kilobytes.
     */
    public static function maxFileSizeKb(): int
    {
        return 10240; // 10MB in KB
    }

    /**
     * Get the maximum number of files per upload.
     */
    public static function maxFilesPerUpload(): int
    {
        return 5;
    }
}
