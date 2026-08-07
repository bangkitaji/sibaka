import { useCallback, useRef, useState } from 'react';

export type RetryStatus = 'idle' | 'loading' | 'success' | 'failed';

interface UseRetryOptions {
  /** Maximum number of retries (default: 3) */
  maxRetries?: number;
  /** Base delay in milliseconds for exponential backoff (default: 1000) */
  baseDelay?: number;
}

interface UseRetryReturn<T> {
  /** Current status of the operation */
  status: RetryStatus;
  /** Error message if failed */
  error: string | null;
  /** Execute the operation with retry logic */
  execute: (fn: () => Promise<T>) => Promise<T | undefined>;
  /** Reset state back to idle */
  reset: () => void;
}

/**
 * Hook implementing retry logic with exponential backoff.
 * Property 5: Retry attempts = min(K+1, 4) with exponential backoff (1s, 2s, 4s).
 */
export function useRetry<T = void>({
  maxRetries = 3,
  baseDelay = 1000,
}: UseRetryOptions = {}): UseRetryReturn<T> {
  const [status, setStatus] = useState<RetryStatus>('idle');
  const [error, setError] = useState<string | null>(null);
  const abortRef = useRef(false);

  const execute = useCallback(
    async (fn: () => Promise<T>): Promise<T | undefined> => {
      abortRef.current = false;
      setStatus('loading');
      setError(null);

      let lastError: Error | null = null;

      for (let attempt = 0; attempt <= maxRetries; attempt++) {
        if (abortRef.current) break;

        try {
          const result = await fn();
          setStatus('success');
          return result;
        } catch (err) {
          lastError = err instanceof Error ? err : new Error(String(err));

          // If not the last attempt, wait with exponential backoff
          if (attempt < maxRetries) {
            const delay = baseDelay * Math.pow(2, attempt); // 1s, 2s, 4s
            await new Promise((resolve) => setTimeout(resolve, delay));
          }
        }
      }

      setStatus('failed');
      setError(
        lastError?.message || 'Operation failed after multiple attempts'
      );
      return undefined;
    },
    [maxRetries, baseDelay]
  );

  const reset = useCallback(() => {
    abortRef.current = true;
    setStatus('idle');
    setError(null);
  }, []);

  return { status, error, execute, reset };
}
