import { useCallback, useRef, useState } from 'react';
import type { ReactionSummary, ReactionType } from '@/types/index.d';

interface UseOptimisticReactionOptions {
  contentId: string;
  initialSummary: ReactionSummary;
}

interface UseOptimisticReactionReturn {
  /** Current reaction summary (optimistically updated) */
  summary: ReactionSummary;
  /** Whether a request is in flight */
  isLoading: boolean;
  /** Error message if last operation failed */
  error: string | null;
  /** Apply a reaction (or change existing one) */
  react: (type: ReactionType) => void;
  /** Remove the current reaction */
  removeReaction: () => void;
}

/**
 * Hook implementing optimistic reaction updates.
 * UI updates within 100ms, reverts on failure.
 * Requirements: 8.1, 8.2, 8.3, 8.6
 */
export function useOptimisticReaction({
  contentId,
  initialSummary,
}: UseOptimisticReactionOptions): UseOptimisticReactionReturn {
  const [summary, setSummary] = useState<ReactionSummary>(initialSummary);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Track inflight to prevent rapid-fire race conditions
  const inflightRef = useRef(false);

  const getCsrfToken = useCallback((): string => {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta?.getAttribute('content') ?? '';
  }, []);

  const react = useCallback(
    (type: ReactionType) => {
      if (inflightRef.current) return;

      // Save previous state for rollback
      const previousSummary = { ...summary };

      // Optimistic update: apply immediately
      const newSummary = computeOptimisticReact(summary, type);
      setSummary(newSummary);
      setError(null);
      setIsLoading(true);
      inflightRef.current = true;

      // Send request in background
      fetch(`/content/${contentId}/reactions`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': getCsrfToken(),
          Accept: 'application/json',
        },
        body: JSON.stringify({ type }),
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error('Reaction could not be completed');
          }
          return response.json();
        })
        .then((json) => {
          // Use server's authoritative state
          setSummary(json.data);
          setError(null);
        })
        .catch(() => {
          // Revert on failure
          setSummary(previousSummary);
          setError('Reaction could not be completed. Please try again.');
        })
        .finally(() => {
          setIsLoading(false);
          inflightRef.current = false;
        });
    },
    [contentId, summary, getCsrfToken]
  );

  const removeReaction = useCallback(() => {
    if (inflightRef.current) return;
    if (!summary.user_reaction) return;

    // Save previous state for rollback
    const previousSummary = { ...summary };

    // Optimistic update: remove immediately
    const newSummary = computeOptimisticRemove(summary);
    setSummary(newSummary);
    setError(null);
    setIsLoading(true);
    inflightRef.current = true;

    fetch(`/content/${contentId}/reactions`, {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': getCsrfToken(),
        Accept: 'application/json',
      },
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error('Reaction could not be removed');
        }
        return response.json();
      })
      .then((json) => {
        setSummary(json.data);
        setError(null);
      })
      .catch(() => {
        setSummary(previousSummary);
        setError('Reaction could not be removed. Please try again.');
      })
      .finally(() => {
        setIsLoading(false);
        inflightRef.current = false;
      });
  }, [contentId, summary, getCsrfToken]);

  return { summary, isLoading, error, react, removeReaction };
}

/**
 * Compute optimistic state when adding/changing a reaction.
 */
function computeOptimisticReact(
  current: ReactionSummary,
  newType: ReactionType
): ReactionSummary {
  const updated = { ...current };
  const previousType = current.user_reaction;

  if (previousType === newType) {
    // Clicking same reaction = remove it
    return computeOptimisticRemove(current);
  }

  if (previousType) {
    // Changing reaction: decrement old type, increment new type (total unchanged)
    updated[previousType] = Math.max(0, updated[previousType] - 1);
    updated[newType] = updated[newType] + 1;
  } else {
    // New reaction: increment type and total
    updated[newType] = updated[newType] + 1;
    updated.total = updated.total + 1;
  }

  updated.user_reaction = newType;
  updated.show_breakdown = updated.total >= 50;
  updated.is_solutif_recommendation = updated.solutif >= 10;

  return updated;
}

/**
 * Compute optimistic state when removing a reaction.
 */
function computeOptimisticRemove(current: ReactionSummary): ReactionSummary {
  const updated = { ...current };
  const previousType = current.user_reaction;

  if (previousType) {
    updated[previousType] = Math.max(0, updated[previousType] - 1);
    updated.total = Math.max(0, updated.total - 1);
  }

  updated.user_reaction = null;
  updated.show_breakdown = updated.total >= 50;
  updated.is_solutif_recommendation = updated.solutif >= 10;

  return updated;
}

export { computeOptimisticReact, computeOptimisticRemove };
