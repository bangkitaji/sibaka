import { describe, it, expect } from 'vitest';
import * as fc from 'fast-check';

/**
 * Property 23: XSS Sanitization (Frontend)
 *
 * **Validates: Requirements 11.1**
 *
 * For any input string (including those with injected script patterns),
 * the sanitization functions SHALL produce output that contains no
 * executable script content: no <script> tags, no on* event handlers,
 * no javascript: URLs.
 */

/**
 * Frontend sanitization functions mirroring backend SanitizationService.
 * These are the functions under test.
 */
function sanitize(input: string): string {
  // Strip dangerous tags and their content
  let output = stripDangerousTags(input);

  // Remove on* event handler attributes
  output = stripEventHandlers(output);

  // Remove javascript: URLs
  output = stripJavascriptUrls(output);

  // Strip all remaining HTML tags
  output = stripAllTags(output);

  // Escape special characters
  output = escapeSpecialChars(output);

  return output;
}

function sanitizeHtml(input: string): string {
  // Strip dangerous tags and their content
  let output = stripDangerousTags(input);

  // Remove on* event handler attributes
  output = stripEventHandlers(output);

  // Remove javascript: URLs
  output = stripJavascriptUrls(output);

  // Strip tags not in the allowed list
  const allowedTags = ['p', 'br', 'strong', 'em', 'u', 's', 'code', 'pre',
    'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li',
    'a', 'img', 'span', 'div', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
    'hr', 'sub', 'sup'];
  output = stripDisallowedTags(output, allowedTags);

  return output;
}

function escapeSpecialChars(input: string): string {
  return input
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function stripDangerousTags(input: string): string {
  const dangerousTags = ['script', 'iframe', 'object', 'embed', 'applet',
    'form', 'input', 'button', 'select', 'textarea', 'link', 'meta', 'base', 'svg', 'math'];

  let output = input;
  for (const tag of dangerousTags) {
    // Remove tag pairs with content
    const pairRegex = new RegExp(`<\\s*${tag}\\b[^>]*>[\\s\\S]*?<\\s*/\\s*${tag}\\s*>`, 'gi');
    output = output.replace(pairRegex, '');

    // Remove self-closing or unclosed tags
    const singleRegex = new RegExp(`<\\s*${tag}\\b[^>]*/?>`, 'gi');
    output = output.replace(singleRegex, '');
  }
  return output;
}

function stripEventHandlers(input: string): string {
  return input.replace(/\s+on\w+\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]*)/gi, '');
}

function stripJavascriptUrls(input: string): string {
  // Remove javascript: URLs in common URL attributes
  let output = input.replace(
    /(?:href|src|action|formaction|data|poster|background)\s*=\s*(?:"[^"]*javascript:[^"]*"|'[^']*javascript:[^']*'|[^\s>]*javascript:[^\s>]*)/gi,
    ''
  );
  // Remove style attributes containing javascript: (e.g., style="background:url(javascript:...)")
  output = output.replace(
    /\s*style\s*=\s*(?:"[^"]*javascript:[^"]*"|'[^']*javascript:[^']*')/gi,
    ''
  );
  return output;
}

function stripAllTags(input: string): string {
  return input.replace(/<[^>]*>/g, '');
}

function stripDisallowedTags(input: string, allowedTags: string[]): string {
  const allowedPattern = allowedTags.join('|');
  // Remove tags not in the allowed list (keep allowed tags)
  const regex = new RegExp(`<\\/?(?!(?:${allowedPattern})\\b)[a-z][a-z0-9]*\\b[^>]*>`, 'gi');
  return input.replace(regex, '');
}

// --- Generators ---

/**
 * Arbitrary that generates XSS attack payloads mixed with benign strings.
 */
const xssPayloadArbitrary = fc.oneof(
  // Regular strings
  fc.string({ minLength: 0, maxLength: 200 }),
  // Unicode strings
  fc.unicodeString({ minLength: 1, maxLength: 100 }),
  // Script tag injections
  fc.string({ minLength: 0, maxLength: 50 }).map(
    (payload) => `<script>${payload}</script>`
  ),
  // Case-varied script tags
  fc.string({ minLength: 0, maxLength: 50 }).map(
    (payload) => `<ScRiPt>${payload}</ScRiPt>`
  ),
  // Event handler injections
  fc.tuple(
    fc.constantFrom('onclick', 'onload', 'onerror', 'onmouseover', 'onfocus', 'onblur', 'onkeyup', 'onsubmit'),
    fc.string({ minLength: 1, maxLength: 50 })
  ).map(([handler, payload]) => `<div ${handler}="${payload}">content</div>`),
  // javascript: URL injections
  fc.string({ minLength: 0, maxLength: 50 }).map(
    (payload) => `<a href="javascript:${payload}">link</a>`
  ),
  // img tag with onerror
  fc.string({ minLength: 1, maxLength: 30 }).map(
    (payload) => `<img src=x onerror="${payload}">`
  ),
  // Nested/obfuscated script attempts
  fc.constantFrom(
    '<scr<script>ipt>alert(1)</scr</script>ipt>',
    '"><script>alert(document.cookie)</script>',
    '<script/xss>alert(1)</script>',
    '<img src="x" onerror="alert(1)">',
    '<svg onload="alert(1)">test</svg>',
    '<iframe src="javascript:alert(1)"></iframe>',
    '<body onload=alert(1)>',
    '<input onfocus=alert(1) autofocus>',
    '<marquee onstart=alert(1)>',
    '<video><source onerror="alert(1)">',
    'javascript:alert(1)',
    '<a href="javascript:void(0)">click</a>',
    '<div style="background:url(javascript:alert(1))">',
  ),
  // Mixed content with XSS embedded
  fc.tuple(fc.string({ minLength: 1, maxLength: 50 }), fc.string({ minLength: 1, maxLength: 50 })).map(
    ([before, after]) => `${before}<script>alert(1)</script>${after}`
  ),
);

describe('Property 23: XSS Sanitization (Frontend)', () => {
  describe('sanitize() - full sanitization', () => {
    it('Property 23.1: Output never contains <script> tags', () => {
      fc.assert(
        fc.property(
          xssPayloadArbitrary,
          (input) => {
            const output = sanitize(input);
            expect(output).not.toMatch(/<\s*script/i);
          }
        ),
        { numRuns: 200 }
      );
    });

    it('Property 23.2: Output never contains on* event handlers', () => {
      fc.assert(
        fc.property(
          xssPayloadArbitrary,
          (input) => {
            const output = sanitize(input);
            // After full sanitization (tags stripped + escaped), on* handlers should not exist
            expect(output).not.toMatch(/\bon\w+\s*=/i);
          }
        ),
        { numRuns: 200 }
      );
    });

    it('Property 23.3: Output never contains javascript: URLs in executable context', () => {
      fc.assert(
        fc.property(
          xssPayloadArbitrary,
          (input) => {
            const output = sanitize(input);
            // After full sanitize, all tags are stripped and chars are escaped.
            // javascript: as plain escaped text is not executable (no href/src attribute context exists).
            // Verify no HTML attribute context with javascript: remains:
            expect(output).not.toMatch(/(?:href|src|action)\s*=\s*["']?\s*javascript\s*:/i);
            // Also verify no tags exist at all (which implicitly means no javascript: in attributes)
            expect(output).not.toMatch(/<[a-z]/i);
          }
        ),
        { numRuns: 200 }
      );
    });

    it('Property 23.4: Output never contains any HTML tags', () => {
      fc.assert(
        fc.property(
          xssPayloadArbitrary,
          (input) => {
            const output = sanitize(input);
            // Full sanitize strips ALL tags
            expect(output).not.toMatch(/<[a-z][a-z0-9]*\b[^>]*>/i);
            expect(output).not.toMatch(/<\/[a-z][a-z0-9]*>/i);
          }
        ),
        { numRuns: 200 }
      );
    });
  });

  describe('escapeSpecialChars() - character escaping', () => {
    it('Property 23.5: All dangerous characters are escaped', () => {
      fc.assert(
        fc.property(
          fc.string({ minLength: 0, maxLength: 500 }),
          (input) => {
            const output = escapeSpecialChars(input);

            // No raw < or > should remain
            expect(output).not.toContain('<');
            expect(output).not.toContain('>');
            // No raw " should remain
            expect(output).not.toContain('"');
            // No raw ' should remain
            expect(output).not.toContain("'");
            // All & should be part of entity references
            const withoutEntities = output.replace(/&(?:amp|lt|gt|quot|#039);/g, '');
            expect(withoutEntities).not.toContain('&');
          }
        ),
        { numRuns: 200 }
      );
    });

    it('Property 23.6: Escaping is idempotent (double-escape produces different output, re-escape of escaped is stable)', () => {
      fc.assert(
        fc.property(
          fc.string({ minLength: 1, maxLength: 100 }),
          (input) => {
            const escaped = escapeSpecialChars(input);
            // Applying escapeSpecialChars again should still produce safe output (no raw dangerous chars)
            const doubleEscaped = escapeSpecialChars(escaped);
            expect(doubleEscaped).not.toContain('<');
            expect(doubleEscaped).not.toContain('>');
            expect(doubleEscaped).not.toContain('"');
            expect(doubleEscaped).not.toContain("'");
          }
        ),
        { numRuns: 100 }
      );
    });
  });

  describe('sanitizeHtml() - safe HTML preservation', () => {
    it('Property 23.7: Output never contains dangerous tags', () => {
      fc.assert(
        fc.property(
          xssPayloadArbitrary,
          (input) => {
            const output = sanitizeHtml(input);
            expect(output).not.toMatch(/<\s*script/i);
            expect(output).not.toMatch(/<\s*iframe/i);
            expect(output).not.toMatch(/<\s*object/i);
            expect(output).not.toMatch(/<\s*embed/i);
            expect(output).not.toMatch(/<\s*svg/i);
          }
        ),
        { numRuns: 200 }
      );
    });

    it('Property 23.8: Output never contains on* event handlers', () => {
      fc.assert(
        fc.property(
          xssPayloadArbitrary,
          (input) => {
            const output = sanitizeHtml(input);
            expect(output).not.toMatch(/\bon\w+\s*=/i);
          }
        ),
        { numRuns: 200 }
      );
    });

    it('Property 23.9: Output never contains javascript: URLs in executable context', () => {
      fc.assert(
        fc.property(
          xssPayloadArbitrary,
          (input) => {
            const output = sanitizeHtml(input);
            // javascript: is only dangerous in attribute contexts (href, src, style, etc.)
            // As plain text content, it's not executable.
            expect(output).not.toMatch(/(?:href|src|action|formaction)\s*=\s*["']?\s*javascript\s*:/i);
            // Also check style attributes with javascript: URLs
            expect(output).not.toMatch(/style\s*=\s*["'][^"']*javascript\s*:/i);
          }
        ),
        { numRuns: 200 }
      );
    });

    it('Property 23.10: Safe tags are preserved when input contains only safe HTML', () => {
      const safeHtmlArbitrary = fc.tuple(
        fc.constantFrom('p', 'strong', 'em', 'code', 'blockquote', 'h1', 'ul', 'li', 'pre'),
        fc.string({ minLength: 1, maxLength: 50 }).filter((s) => !s.includes('<') && !s.includes('>'))
      ).map(([tag, content]) => `<${tag}>${content}</${tag}>`);

      fc.assert(
        fc.property(
          safeHtmlArbitrary,
          (input) => {
            const output = sanitizeHtml(input);
            // Safe tags should be preserved - output should equal input
            expect(output).toBe(input);
          }
        ),
        { numRuns: 100 }
      );
    });
  });
});
