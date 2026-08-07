import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useAutoSave } from '../../../resources/js/Hooks/useAutoSave';

/**
 * Unit tests for useAutoSave hook
 * Tests auto-save every 10 seconds, status indicator behavior, and retry logic.
 *
 * Validates: Requirements 3.4, 3.5, 3.6, 10.2, 10.3
 */

// Mock fetch
const mockFetch = vi.fn();
global.fetch = mockFetch;

// Mock CSRF meta tag
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

describe('useAutoSave', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    setupCsrfMeta();
    mockFetch.mockReset();
  });

  afterEach(() => {
    vi.useRealTimers();
    removeCsrfMeta();
  });

  it('starts with idle status', () => {
    const { result } = renderHook(() =>
      useAutoSave({
        contentId: 'test-id',
        content: 'initial content',
        enabled: false,
      })
    );

    expect(result.current.status).toBe('idle');
    expect(result.current.lastSaved).toBeNull();
    expect(result.current.hasUnsavedChanges).toBe(false);
  });

  it('saves every 10 seconds by default', async () => {
    mockFetch.mockResolvedValue({ ok: true, json: () => Promise.resolve({}) });

    const { result, rerender } = renderHook(
      ({ content }) =>
        useAutoSave({
          contentId: 'test-id',
          content,
          interval: 10_000,
        }),
      { initialProps: { content: 'initial content' } }
    );

    // Change content to trigger unsaved changes
    rerender({ content: 'updated content' });
    expect(result.current.hasUnsavedChanges).toBe(true);

    // Advance 10 seconds to trigger auto-save
    await act(async () => {
      await vi.advanceTimersByTimeAsync(10_000);
    });

    expect(mockFetch).toHaveBeenCalledTimes(1);
    expect(result.current.status).toBe('saved');
  });

  it('does not save if content has not changed', async () => {
    mockFetch.mockResolvedValue({ ok: true, json: () => Promise.resolve({}) });

    renderHook(() =>
      useAutoSave({
        contentId: 'test-id',
        content: 'same content',
        interval: 10_000,
      })
    );

    // Advance 10 seconds - should not trigger save because content hasn't changed
    await act(async () => {
      await vi.advanceTimersByTimeAsync(10_000);
    });

    expect(mockFetch).not.toHaveBeenCalled();
  });

  it('shows "saving" status while saving', async () => {
    let resolvePromise: (value: Response) => void;
    mockFetch.mockReturnValue(
      new Promise<Response>((resolve) => {
        resolvePromise = resolve;
      })
    );

    const { result, rerender } = renderHook(
      ({ content }) =>
        useAutoSave({
          contentId: 'test-id',
          content,
          interval: 10_000,
        }),
      { initialProps: { content: 'initial' } }
    );

    rerender({ content: 'changed' });

    // Trigger interval
    act(() => {
      vi.advanceTimersByTime(10_000);
    });

    // Status should be 'saving' while fetch is pending
    expect(result.current.status).toBe('saving');

    // Resolve fetch
    await act(async () => {
      resolvePromise!({ ok: true, json: () => Promise.resolve({}) } as Response);
      await vi.advanceTimersByTimeAsync(0);
    });

    expect(result.current.status).toBe('saved');
  });

  it('shows "saved" status for 2 seconds then returns to idle', async () => {
    mockFetch.mockResolvedValue({ ok: true, json: () => Promise.resolve({}) });

    const { result, rerender } = renderHook(
      ({ content }) =>
        useAutoSave({
          contentId: 'test-id',
          content,
          interval: 10_000,
        }),
      { initialProps: { content: 'initial' } }
    );

    rerender({ content: 'updated' });

    await act(async () => {
      await vi.advanceTimersByTimeAsync(10_000);
    });

    expect(result.current.status).toBe('saved');

    // Advance 2 seconds - should go back to idle
    await act(async () => {
      await vi.advanceTimersByTimeAsync(2_000);
    });

    expect(result.current.status).toBe('idle');
  });

  it('retries 3 times on failure with 2-second delay', async () => {
    // Fail all attempts
    mockFetch.mockRejectedValue(new Error('Network error'));

    const { result, rerender } = renderHook(
      ({ content }) =>
        useAutoSave({
          contentId: 'test-id',
          content,
          interval: 10_000,
          maxRetries: 3,
          retryDelay: 2_000,
        }),
      { initialProps: { content: 'initial' } }
    );

    rerender({ content: 'changed' });

    // Trigger the interval
    await act(async () => {
      await vi.advanceTimersByTimeAsync(10_000);
    });

    // First attempt fails, wait for retries
    await act(async () => {
      await vi.advanceTimersByTimeAsync(2_000); // retry 1
    });
    await act(async () => {
      await vi.advanceTimersByTimeAsync(2_000); // retry 2
    });
    await act(async () => {
      await vi.advanceTimersByTimeAsync(2_000); // retry 3
    });

    // 1 initial + 3 retries = 4 attempts
    expect(mockFetch).toHaveBeenCalledTimes(4);
    expect(result.current.status).toBe('failed');
  });

  it('marks hasUnsavedChanges true when content changes', () => {
    const { result, rerender } = renderHook(
      ({ content }) =>
        useAutoSave({
          contentId: 'test-id',
          content,
          enabled: false,
        }),
      { initialProps: { content: 'initial' } }
    );

    expect(result.current.hasUnsavedChanges).toBe(false);

    rerender({ content: 'changed' });
    expect(result.current.hasUnsavedChanges).toBe(true);
  });

  it('resets hasUnsavedChanges after successful save', async () => {
    mockFetch.mockResolvedValue({ ok: true, json: () => Promise.resolve({}) });

    const { result, rerender } = renderHook(
      ({ content }) =>
        useAutoSave({
          contentId: 'test-id',
          content,
          interval: 10_000,
        }),
      { initialProps: { content: 'initial' } }
    );

    rerender({ content: 'changed' });
    expect(result.current.hasUnsavedChanges).toBe(true);

    await act(async () => {
      await vi.advanceTimersByTimeAsync(10_000);
    });

    expect(result.current.hasUnsavedChanges).toBe(false);
  });

  it('manual save triggers immediately', async () => {
    mockFetch.mockResolvedValue({ ok: true, json: () => Promise.resolve({}) });

    const { result, rerender } = renderHook(
      ({ content }) =>
        useAutoSave({
          contentId: 'test-id',
          content,
          interval: 10_000,
          enabled: false,
        }),
      { initialProps: { content: 'initial' } }
    );

    rerender({ content: 'changed' });

    await act(async () => {
      await result.current.save();
    });

    expect(mockFetch).toHaveBeenCalledTimes(1);
    expect(result.current.status).toBe('saved');
  });
});
