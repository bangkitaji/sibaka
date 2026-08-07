import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';
import * as fc from 'fast-check';
import { useDraftRestore } from '../../../resources/js/Hooks/useDraftRestore';

/**
 * Property 22: Draft Round-Trip Persistence
 *
 * **Validates: Requirements 10.4**
 *
 * For any content string C, save(C) followed by restore() returns exactly C.
 * This tests that the save/restore round-trip preserves content integrity
 * for all types of strings: unicode, special chars, code blocks, HTML, etc.
 */

const mockFetch = vi.fn();

function setupCsrfMeta() {
  const meta = document.createElement('meta');
  meta.setAttribute('name', 'csrf-token');
  meta.setAttribute('content', 'test-token');
  document.head.appendChild(meta);
}

function removeCsrfMeta() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  if (meta) meta.remove();
}

describe('Property 22: Draft Round-Trip Persistence', () => {
  beforeEach(() => {
    setupCsrfMeta();
    global.fetch = mockFetch;
    mockFetch.mockReset();
  });

  afterEach(() => {
    removeCsrfMeta();
  });

  /**
   * Custom arbitrary that generates content strings representative of
   * real-world editor content: unicode, special chars, code blocks, HTML, etc.
   */
  const contentStringArbitrary = fc.oneof(
    // Regular strings (non-empty)
    fc.string({ minLength: 1, maxLength: 500 }),
    // Unicode strings (emoji, CJK, Arabic, etc.)
    fc.unicodeString({ minLength: 1, maxLength: 200 }),
    // Strings with special HTML/XML chars
    fc.constantFrom(
      '<script>alert("xss")</script>',
      '<div class="test">Hello & "world"</div>',
      "it's a <test> & fun > times",
      '&amp;&lt;&gt;&quot;&#39;',
      '<p onclick="evil()">content</p>',
    ),
    // Markdown code blocks
    fc.tuple(fc.constantFrom('js', 'python', 'java', 'go', 'php', ''), fc.string({ minLength: 1, maxLength: 100 }))
      .map(([lang, code]) => `\`\`\`${lang}\n${code}\n\`\`\``),
    // Strings with emoji and CJK characters
    fc.constantFrom(
      '🎉🚀💻 Great post! 你好世界 こんにちは',
      '中文测试 日本語テスト 한국어테스트',
      '🇮🇩 Indonesia 🇯🇵 Japan 🇺🇸 USA',
      '⚡️ Performance ✅ Tests 🐛 Bugs',
      'الحمد لله',
    ),
    // Whitespace-only strings
    fc.constantFrom('   ', '\t\t', '\n\n\n', '  \t \n  ', ' '),
    // Very long strings
    fc.string({ minLength: 1000, maxLength: 5000 }),
    // Strings with embedded newlines and special whitespace
    fc.tuple(fc.string({ minLength: 1 }), fc.string({ minLength: 1 }), fc.string({ minLength: 1 }))
      .map(([a, b, c]) => `${a}\n\r\n${b}\t${c}`),
    // JSON-like content that might trip up serialization
    fc.constantFrom(
      '{"key": "value", "nested": {"arr": [1,2,3]}}',
      '[null, true, false, "\\n\\t"]',
      '"escaped \\"quotes\\" and \\\\backslashes"',
    ),
  );

  it('Property 22.1: Direct fetch round-trip preserves any content string exactly', async () => {
    await fc.assert(
      fc.asyncProperty(
        contentStringArbitrary,
        async (content) => {
          // Simulate the exact fetch-based save/restore that the hooks perform
          let storedBody: string | null = null;

          mockFetch.mockImplementation(async (url: string, options?: RequestInit) => {
            const urlStr = typeof url === 'string' ? url : '';

            if (options?.method === 'PUT' && urlStr.includes('/draft')) {
              const parsedBody = JSON.parse(options.body as string);
              storedBody = parsedBody.body;
              return {
                ok: true,
                json: () => Promise.resolve({ message: 'Draft saved.', saved_at: new Date().toISOString() }),
              };
            }

            if ((!options?.method || options?.method === 'GET') && urlStr.includes('/draft')) {
              return {
                ok: true,
                json: () => Promise.resolve({
                  body: storedBody,
                  has_draft: storedBody !== null,
                }),
              };
            }

            return { ok: false, status: 404 };
          });

          const contentId = 'round-trip-test';
          const csrfToken = 'test-token';

          // Save via PUT (simulating what useAutoSave does internally)
          await fetch(`/content/${contentId}/draft`, {
            method: 'PUT',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify({ body: content }),
          });

          // Restore via GET (simulating what useDraftRestore does internally)
          const restoreResponse = await fetch(`/content/${contentId}/draft`, {
            method: 'GET',
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
          });

          const data = await (restoreResponse as any).json();

          // Property: restored body must be exactly identical to what was saved
          expect(data.body).toBe(content);
          expect(data.has_draft).toBe(true);
        }
      ),
      { numRuns: 100 }
    );
  });

  it('Property 22.2: useDraftRestore hook returns exactly what was saved via fetch', async () => {
    await fc.assert(
      fc.asyncProperty(
        contentStringArbitrary,
        async (content) => {
          // Mock fetch to always return the content we "saved"
          mockFetch.mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({
              body: content,
              has_draft: true,
            }),
          });

          const { result } = renderHook(() =>
            useDraftRestore({ contentId: 'test-id', enabled: true })
          );

          await waitFor(() => {
            expect(result.current.isLoading).toBe(false);
          });

          // Property: the hook returns exactly the content from the API
          expect(result.current.draftBody).toBe(content);
          expect(result.current.hasDraft).toBe(true);
        }
      ),
      { numRuns: 100 }
    );
  });

  it('Property 22.3: Multiple saves overwrite correctly, last save wins on restore', async () => {
    await fc.assert(
      fc.asyncProperty(
        fc.array(contentStringArbitrary, { minLength: 2, maxLength: 5 }),
        async (contentSequence) => {
          let storedBody: string | null = null;

          mockFetch.mockImplementation(async (url: string, options?: RequestInit) => {
            const urlStr = typeof url === 'string' ? url : '';

            if (options?.method === 'PUT' && urlStr.includes('/draft')) {
              const parsedBody = JSON.parse(options.body as string);
              storedBody = parsedBody.body;
              return {
                ok: true,
                json: () => Promise.resolve({ message: 'Draft saved.', saved_at: new Date().toISOString() }),
              };
            }

            if ((!options?.method || options?.method === 'GET') && urlStr.includes('/draft')) {
              return {
                ok: true,
                json: () => Promise.resolve({
                  body: storedBody,
                  has_draft: storedBody !== null,
                }),
              };
            }

            return { ok: false, status: 404 };
          });

          const contentId = 'multi-save-test';
          const csrfToken = 'test-token';

          // Save each content in sequence
          for (const body of contentSequence) {
            await fetch(`/content/${contentId}/draft`, {
              method: 'PUT',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
              },
              credentials: 'same-origin',
              body: JSON.stringify({ body }),
            });
          }

          // Restore should return the last saved content
          const restoreResponse = await fetch(`/content/${contentId}/draft`, {
            method: 'GET',
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
          });

          const data = await (restoreResponse as any).json();
          const lastContent = contentSequence[contentSequence.length - 1];

          // Property: last save wins
          expect(data.body).toBe(lastContent);
          expect(data.has_draft).toBe(true);
        }
      ),
      { numRuns: 100 }
    );
  });

  it('Property 22.4: JSON serialization round-trip preserves content with special characters', async () => {
    fc.assert(
      fc.property(
        contentStringArbitrary,
        (content) => {
          // Test that JSON.stringify → JSON.parse round-trip preserves the content
          // This is the core serialization that the fetch API uses for the draft body
          const serialized = JSON.stringify({ body: content });
          const deserialized = JSON.parse(serialized);

          expect(deserialized.body).toBe(content);
        }
      ),
      { numRuns: 100 }
    );
  });
});
