import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import {
  useOptimisticReaction,
  computeOptimisticReact,
  computeOptimisticRemove,
} from '../../../resources/js/Hooks/useOptimisticReaction';
import type { ReactionSummary } from '../../../resources/js/types/index.d';

/**
 * Unit tests for useOptimisticReaction hook.
 * Tests optimistic UI updates, server sync, and rollback on failure.
 *
 * Validates: Requirements 8.1, 8.2, 8.3, 8.4, 8.5, 8.6
 */

const mockFetch = vi.fn();
global.fetch = mockFetch;

function setupCsrfMeta() {
  const meta = document.createElement('meta');
  meta.setAttribute('name', 'csrf-token');
  meta.setAttribute('content', 'test-csrf-token');
  document.head.appendChild(meta);
}

function removeCsrfMeta() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  if (meta) meta.remove();
}

function createInitialSummary(overrides: Partial<ReactionSummary> = {}): ReactionSummary {
  return {
    total: 0,
    insightful: 0,
    relatable: 0,
    helpful: 0,
    solutif: 0,
    user_reaction: null,
    show_breakdown: false,
    is_solutif_recommendation: false,
    ...overrides,
  };
}

describe('useOptimisticReaction', () => {
  beforeEach(() => {
    setupCsrfMeta();
    mockFetch.mockReset();
  });

  afterEach(() => {
    removeCsrfMeta();
  });

  it('initializes with the provided summary', () => {
    const initial = createInitialSummary({ total: 5, insightful: 3, helpful: 2 });

    const { result } = renderHook(() =>
      useOptimisticReaction({ contentId: 'content-1', initialSummary: initial })
    );

    expect(result.current.summary.total).toBe(5);
    expect(result.current.summary.insightful).toBe(3);
    expect(result.current.summary.helpful).toBe(2);
    expect(result.current.isLoading).toBe(false);
    expect(result.current.error).toBeNull();
  });

  it('optimistically updates UI when adding a reaction', async () => {
    const serverResponse = createInitialSummary({
      total: 1,
      insightful: 1,
      user_reaction: 'insightful',
    });
    mockFetch.mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ data: serverResponse }),
    });

    const initial = createInitialSummary();

    const { result } = renderHook(() =>
      useOptimisticReaction({ contentId: 'content-1', initialSummary: initial })
    );

    // Click insightful
    act(() => {
      result.current.react('insightful');
    });

    // Should update immediately (optimistic)
    expect(result.current.summary.insightful).toBe(1);
    expect(result.current.summary.total).toBe(1);
    expect(result.current.summary.user_reaction).toBe('insightful');
    expect(result.current.isLoading).toBe(true);

    // Wait for fetch to resolve
    await act(async () => {
      await new Promise((r) => setTimeout(r, 0));
    });

    expect(result.current.isLoading).toBe(false);
    expect(result.current.error).toBeNull();
  });

  it('optimistically updates UI when changing a reaction', async () => {
    const serverResponse = createInitialSummary({
      total: 1,
      helpful: 1,
      user_reaction: 'helpful',
    });
    mockFetch.mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ data: serverResponse }),
    });

    const initial = createInitialSummary({
      total: 1,
      insightful: 1,
      user_reaction: 'insightful',
    });

    const { result } = renderHook(() =>
      useOptimisticReaction({ contentId: 'content-1', initialSummary: initial })
    );

    // Change from insightful to helpful
    act(() => {
      result.current.react('helpful');
    });

    // Optimistic: insightful should decrease, helpful increase, total stays
    expect(result.current.summary.insightful).toBe(0);
    expect(result.current.summary.helpful).toBe(1);
    expect(result.current.summary.total).toBe(1);
    expect(result.current.summary.user_reaction).toBe('helpful');

    await act(async () => {
      await new Promise((r) => setTimeout(r, 0));
    });
  });

  it('removes reaction when clicking same type (toggle off)', async () => {
    const serverResponse = createInitialSummary({
      total: 0,
      user_reaction: null,
    });
    mockFetch.mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ data: serverResponse }),
    });

    const initial = createInitialSummary({
      total: 1,
      insightful: 1,
      user_reaction: 'insightful',
    });

    const { result } = renderHook(() =>
      useOptimisticReaction({ contentId: 'content-1', initialSummary: initial })
    );

    // Click insightful again to toggle off
    act(() => {
      result.current.react('insightful');
    });

    // Optimistic: should remove reaction
    expect(result.current.summary.insightful).toBe(0);
    expect(result.current.summary.total).toBe(0);
    expect(result.current.summary.user_reaction).toBeNull();

    await act(async () => {
      await new Promise((r) => setTimeout(r, 0));
    });
  });

  it('reverts on API failure', async () => {
    mockFetch.mockResolvedValue({
      ok: false,
      status: 500,
      json: () => Promise.resolve({ message: 'Server error' }),
    });

    const initial = createInitialSummary({ total: 5, insightful: 3, helpful: 2 });

    const { result } = renderHook(() =>
      useOptimisticReaction({ contentId: 'content-1', initialSummary: initial })
    );

    // React
    act(() => {
      result.current.react('solutif');
    });

    // Optimistic update applied
    expect(result.current.summary.solutif).toBe(1);
    expect(result.current.summary.total).toBe(6);

    // Wait for fetch to fail
    await act(async () => {
      await new Promise((r) => setTimeout(r, 0));
    });

    // Should revert to original
    expect(result.current.summary.total).toBe(5);
    expect(result.current.summary.insightful).toBe(3);
    expect(result.current.summary.helpful).toBe(2);
    expect(result.current.summary.solutif).toBe(0);
    expect(result.current.summary.user_reaction).toBeNull();
    expect(result.current.error).toBe('Reaction could not be completed. Please try again.');
  });

  it('reverts removeReaction on API failure', async () => {
    mockFetch.mockResolvedValue({
      ok: false,
      status: 500,
    });

    const initial = createInitialSummary({
      total: 1,
      helpful: 1,
      user_reaction: 'helpful',
    });

    const { result } = renderHook(() =>
      useOptimisticReaction({ contentId: 'content-1', initialSummary: initial })
    );

    act(() => {
      result.current.removeReaction();
    });

    // Optimistic: removed
    expect(result.current.summary.helpful).toBe(0);
    expect(result.current.summary.total).toBe(0);
    expect(result.current.summary.user_reaction).toBeNull();

    await act(async () => {
      await new Promise((r) => setTimeout(r, 0));
    });

    // Should revert
    expect(result.current.summary.helpful).toBe(1);
    expect(result.current.summary.total).toBe(1);
    expect(result.current.summary.user_reaction).toBe('helpful');
    expect(result.current.error).toBe('Reaction could not be removed. Please try again.');
  });

  it('sends POST request with correct payload', async () => {
    mockFetch.mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ data: createInitialSummary() }),
    });

    const { result } = renderHook(() =>
      useOptimisticReaction({
        contentId: 'test-content-id',
        initialSummary: createInitialSummary(),
      })
    );

    act(() => {
      result.current.react('relatable');
    });

    await act(async () => {
      await new Promise((r) => setTimeout(r, 0));
    });

    expect(mockFetch).toHaveBeenCalledWith(
      '/content/test-content-id/reactions',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({ type: 'relatable' }),
      })
    );
  });

  it('sends DELETE request when removing reaction', async () => {
    mockFetch.mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ data: createInitialSummary() }),
    });

    const initial = createInitialSummary({
      total: 1,
      relatable: 1,
      user_reaction: 'relatable',
    });

    const { result } = renderHook(() =>
      useOptimisticReaction({ contentId: 'test-content-id', initialSummary: initial })
    );

    act(() => {
      result.current.removeReaction();
    });

    await act(async () => {
      await new Promise((r) => setTimeout(r, 0));
    });

    expect(mockFetch).toHaveBeenCalledWith(
      '/content/test-content-id/reactions',
      expect.objectContaining({
        method: 'DELETE',
      })
    );
  });

  it('does not send request when removeReaction is called with no active reaction', () => {
    const initial = createInitialSummary();

    const { result } = renderHook(() =>
      useOptimisticReaction({ contentId: 'content-1', initialSummary: initial })
    );

    act(() => {
      result.current.removeReaction();
    });

    expect(mockFetch).not.toHaveBeenCalled();
  });
});

describe('computeOptimisticReact', () => {
  it('adds a new reaction (no existing reaction)', () => {
    const current = createInitialSummary({ total: 3, insightful: 2, helpful: 1 });
    const result = computeOptimisticReact(current, 'solutif');

    expect(result.solutif).toBe(1);
    expect(result.total).toBe(4);
    expect(result.user_reaction).toBe('solutif');
  });

  it('changes reaction (existing reaction present)', () => {
    const current = createInitialSummary({
      total: 5,
      insightful: 3,
      helpful: 2,
      user_reaction: 'insightful',
    });
    const result = computeOptimisticReact(current, 'helpful');

    expect(result.insightful).toBe(2);
    expect(result.helpful).toBe(3);
    expect(result.total).toBe(5); // unchanged
    expect(result.user_reaction).toBe('helpful');
  });

  it('toggles off when clicking same reaction', () => {
    const current = createInitialSummary({
      total: 1,
      insightful: 1,
      user_reaction: 'insightful',
    });
    const result = computeOptimisticReact(current, 'insightful');

    expect(result.insightful).toBe(0);
    expect(result.total).toBe(0);
    expect(result.user_reaction).toBeNull();
  });

  it('sets show_breakdown true at 50+ total', () => {
    const current = createInitialSummary({ total: 49, insightful: 49 });
    const result = computeOptimisticReact(current, 'helpful');

    expect(result.total).toBe(50);
    expect(result.show_breakdown).toBe(true);
  });

  it('sets is_solutif_recommendation true at 10+ solutif', () => {
    const current = createInitialSummary({ total: 50, solutif: 9 });
    const result = computeOptimisticReact(current, 'solutif');

    expect(result.solutif).toBe(10);
    expect(result.is_solutif_recommendation).toBe(true);
  });
});

describe('computeOptimisticRemove', () => {
  it('removes a reaction and decrements counters', () => {
    const current = createInitialSummary({
      total: 5,
      helpful: 2,
      user_reaction: 'helpful',
    });
    const result = computeOptimisticRemove(current);

    expect(result.helpful).toBe(1);
    expect(result.total).toBe(4);
    expect(result.user_reaction).toBeNull();
  });

  it('does not go below zero', () => {
    const current = createInitialSummary({
      total: 0,
      insightful: 0,
      user_reaction: 'insightful',
    });
    const result = computeOptimisticRemove(current);

    expect(result.insightful).toBe(0);
    expect(result.total).toBe(0);
    expect(result.user_reaction).toBeNull();
  });

  it('sets show_breakdown false when dropping below 50', () => {
    const current = createInitialSummary({
      total: 50,
      insightful: 50,
      show_breakdown: true,
      user_reaction: 'insightful',
    });
    const result = computeOptimisticRemove(current);

    expect(result.total).toBe(49);
    expect(result.show_breakdown).toBe(false);
  });

  it('sets is_solutif_recommendation false when solutif drops below 10', () => {
    const current = createInitialSummary({
      total: 50,
      solutif: 10,
      is_solutif_recommendation: true,
      user_reaction: 'solutif',
    });
    const result = computeOptimisticRemove(current);

    expect(result.solutif).toBe(9);
    expect(result.is_solutif_recommendation).toBe(false);
  });
});
