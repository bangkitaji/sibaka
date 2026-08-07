import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';
import { useDraftRestore } from '../../../resources/js/Hooks/useDraftRestore';

/**
 * Unit tests for useDraftRestore hook
 * Tests draft restoration on editor reopen.
 *
 * Validates: Requirements 10.4
 */

const mockFetch = vi.fn();
global.fetch = mockFetch;

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

describe('useDraftRestore', () => {
  beforeEach(() => {
    setupCsrfMeta();
    mockFetch.mockReset();
  });

  afterEach(() => {
    removeCsrfMeta();
  });

  it('fetches draft on mount when enabled', async () => {
    mockFetch.mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ body: '<p>Draft content</p>', has_draft: true }),
    });

    const { result } = renderHook(() =>
      useDraftRestore({ contentId: 'test-id' })
    );

    expect(result.current.isLoading).toBe(true);

    await waitFor(() => {
      expect(result.current.isLoading).toBe(false);
    });

    expect(result.current.hasDraft).toBe(true);
    expect(result.current.draftBody).toBe('<p>Draft content</p>');
  });

  it('does not fetch when disabled', () => {
    const { result } = renderHook(() =>
      useDraftRestore({ contentId: 'test-id', enabled: false })
    );

    expect(mockFetch).not.toHaveBeenCalled();
    expect(result.current.isLoading).toBe(false);
    expect(result.current.hasDraft).toBe(false);
  });

  it('sets hasDraft to false when no draft available', async () => {
    mockFetch.mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ body: null, has_draft: false }),
    });

    const { result } = renderHook(() =>
      useDraftRestore({ contentId: 'test-id' })
    );

    await waitFor(() => {
      expect(result.current.isLoading).toBe(false);
    });

    expect(result.current.hasDraft).toBe(false);
    expect(result.current.draftBody).toBeNull();
  });

  it('handles fetch errors gracefully', async () => {
    mockFetch.mockRejectedValue(new Error('Network error'));

    const { result } = renderHook(() =>
      useDraftRestore({ contentId: 'test-id' })
    );

    await waitFor(() => {
      expect(result.current.isLoading).toBe(false);
    });

    expect(result.current.hasDraft).toBe(false);
    expect(result.current.draftBody).toBeNull();
  });

  it('acceptDraft returns the body and clears hasDraft', async () => {
    mockFetch.mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ body: '<p>Restored</p>', has_draft: true }),
    });

    const { result } = renderHook(() =>
      useDraftRestore({ contentId: 'test-id' })
    );

    await waitFor(() => {
      expect(result.current.hasDraft).toBe(true);
    });

    let body: string | null = null;
    act(() => {
      body = result.current.acceptDraft();
    });

    expect(body).toBe('<p>Restored</p>');
    expect(result.current.hasDraft).toBe(false);
  });

  it('dismissDraft clears the draft state', async () => {
    mockFetch.mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ body: '<p>Draft</p>', has_draft: true }),
    });

    const { result } = renderHook(() =>
      useDraftRestore({ contentId: 'test-id' })
    );

    await waitFor(() => {
      expect(result.current.hasDraft).toBe(true);
    });

    act(() => {
      result.current.dismissDraft();
    });

    expect(result.current.hasDraft).toBe(false);
    expect(result.current.draftBody).toBeNull();
  });
});
