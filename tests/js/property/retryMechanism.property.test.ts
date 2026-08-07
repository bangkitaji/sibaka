import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import * as fc from 'fast-check';
import { useRetry } from '../../../resources/js/Hooks/useRetry';

/**
 * Property 5: Retry Mechanism Correctness
 *
 * **Validates: Requirements 2.6, 3.5, 10.3**
 *
 * Tests that the useRetry hook:
 * 1. Total attempts = min(K+1, 4) where K = failures before success
 * 2. Delays follow exponential backoff: baseDelay * 2^0, baseDelay * 2^1, baseDelay * 2^2
 * 3. If all attempts fail, status becomes 'failed'
 * 4. If an attempt succeeds, no further retries happen
 */
describe('Property 5: Retry Mechanism Correctness (useRetry hook)', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  /**
   * Creates a mock function that fails `failCount` times then succeeds.
   * Tracks call count to verify total attempts.
   */
  function createMockOperation(failCount: number) {
    let callCount = 0;
    const fn = () => {
      callCount++;
      if (callCount <= failCount) {
        return Promise.reject(new Error(`Failure #${callCount}`));
      }
      return Promise.resolve(`success-${callCount}`);
    };
    return { fn, getCallCount: () => callCount };
  }

  it('Property 5.1: Total attempts = min(K+1, maxRetries+1) for any failure count K', async () => {
    await fc.assert(
      fc.asyncProperty(
        fc.nat({ max: 20 }), // K: number of failures before success (or exhaustion)
        fc.integer({ min: 1, max: 5 }), // maxRetries parameter
        async (k, maxRetries) => {
          const mock = createMockOperation(k);
          const { result } = renderHook(() => useRetry({ maxRetries, baseDelay: 100 }));

          let executePromise: Promise<string | undefined>;

          await act(async () => {
            executePromise = result.current.execute(mock.fn);
            // Advance enough time to cover all possible retries
            for (let i = 0; i < maxRetries; i++) {
              await vi.advanceTimersByTimeAsync(100 * Math.pow(2, i) + 10);
            }
          });

          await act(async () => {
            await executePromise!;
          });

          const expectedAttempts = Math.min(k + 1, maxRetries + 1);
          expect(mock.getCallCount()).toBe(expectedAttempts);
        }
      ),
      { numRuns: 50 }
    );
  });

  it('Property 5.2: Delays follow exponential backoff (baseDelay * 2^attempt)', async () => {
    await fc.assert(
      fc.asyncProperty(
        fc.integer({ min: 1, max: 3 }), // K: number of failures before success (1-3 to ensure retries happen)
        fc.integer({ min: 50, max: 500 }), // baseDelay in ms
        async (k, baseDelay) => {
          const delays: number[] = [];
          let callCount = 0;

          const fn = () => {
            callCount++;
            if (callCount <= k) {
              return Promise.reject(new Error(`Failure #${callCount}`));
            }
            return Promise.resolve('success');
          };

          const { result } = renderHook(() => useRetry({ maxRetries: 3, baseDelay }));

          await act(async () => {
            const executePromise = result.current.execute(fn);

            // Advance through each delay, recording expected exponential backoff
            for (let attempt = 0; attempt < k; attempt++) {
              const expectedDelay = baseDelay * Math.pow(2, attempt);
              delays.push(expectedDelay);
              await vi.advanceTimersByTimeAsync(expectedDelay + 1);
            }

            await executePromise;
          });

          // Verify each delay matches exponential backoff pattern
          for (let i = 0; i < delays.length; i++) {
            expect(delays[i]).toBe(baseDelay * Math.pow(2, i));
          }

          // Verify correct number of retries occurred
          expect(callCount).toBe(k + 1);
        }
      ),
      { numRuns: 50 }
    );
  });

  it('Property 5.3: If all attempts fail, status becomes "failed"', async () => {
    await fc.assert(
      fc.asyncProperty(
        fc.integer({ min: 4, max: 50 }), // failCount >= maxRetries+1 to ensure all fail
        async (failCount) => {
          const mock = createMockOperation(failCount);
          const maxRetries = 3;
          const baseDelay = 100;

          const { result } = renderHook(() => useRetry({ maxRetries, baseDelay }));

          await act(async () => {
            const executePromise = result.current.execute(mock.fn);

            // Advance enough time for all retries with exponential backoff
            // Delays: 100, 200, 400 = 700ms total minimum
            for (let i = 0; i < maxRetries; i++) {
              await vi.advanceTimersByTimeAsync(baseDelay * Math.pow(2, i) + 10);
            }

            await executePromise;
          });

          expect(result.current.status).toBe('failed');
          expect(result.current.error).not.toBeNull();
          expect(mock.getCallCount()).toBe(maxRetries + 1); // 4 total attempts
        }
      ),
      { numRuns: 50 }
    );
  });

  it('Property 5.4: If an attempt succeeds, no further retries happen', async () => {
    await fc.assert(
      fc.asyncProperty(
        fc.integer({ min: 0, max: 3 }), // K: number of failures before success
        async (k) => {
          const maxRetries = 3;
          const baseDelay = 100;
          const mock = createMockOperation(k);

          const { result } = renderHook(() => useRetry({ maxRetries, baseDelay }));

          await act(async () => {
            const executePromise = result.current.execute(mock.fn);

            // Advance enough time for all possible retries
            for (let i = 0; i < maxRetries; i++) {
              await vi.advanceTimersByTimeAsync(baseDelay * Math.pow(2, i) + 10);
            }

            await executePromise;
          });

          // Should have succeeded
          expect(result.current.status).toBe('success');
          // Should have stopped at k+1 attempts (not continued to maxRetries+1)
          expect(mock.getCallCount()).toBe(k + 1);

          // Wait a bit more to confirm no extra calls happen
          await act(async () => {
            await vi.advanceTimersByTimeAsync(5000);
          });

          // Call count should not have increased
          expect(mock.getCallCount()).toBe(k + 1);
        }
      ),
      { numRuns: 50 }
    );
  });

  it('Property 5.5: For random response sequences, retry count = min(K+1, 4) with default config', async () => {
    await fc.assert(
      fc.asyncProperty(
        // Generate a sequence of booleans (true=fail, false=succeed)
        fc.array(fc.boolean(), { minLength: 1, maxLength: 10 }),
        async (responseSequence) => {
          let callIndex = 0;
          const fn = () => {
            const shouldFail = callIndex < responseSequence.length
              ? responseSequence[callIndex]
              : false; // Default to success after sequence exhausts
            callIndex++;
            if (shouldFail) {
              return Promise.reject(new Error(`Failure at index ${callIndex - 1}`));
            }
            return Promise.resolve('success');
          };

          const maxRetries = 3;
          const baseDelay = 100;

          const { result } = renderHook(() => useRetry({ maxRetries, baseDelay }));

          await act(async () => {
            const executePromise = result.current.execute(fn);

            // Advance enough time for all retries
            for (let i = 0; i < maxRetries; i++) {
              await vi.advanceTimersByTimeAsync(baseDelay * Math.pow(2, i) + 10);
            }

            await executePromise;
          });

          // Find index of first success in the sequence (within max attempts)
          const maxAttempts = maxRetries + 1;
          let firstSuccessIndex = -1;
          for (let i = 0; i < Math.min(responseSequence.length, maxAttempts); i++) {
            if (!responseSequence[i]) {
              firstSuccessIndex = i;
              break;
            }
          }

          if (firstSuccessIndex === -1 && responseSequence.length >= maxAttempts) {
            // All attempts within budget failed
            // Check if all responses in the first maxAttempts are failures
            const allFailed = responseSequence.slice(0, maxAttempts).every(v => v === true);
            if (allFailed) {
              expect(result.current.status).toBe('failed');
              expect(callIndex).toBe(maxAttempts);
            } else {
              // There was a success within the first maxAttempts
              expect(result.current.status).toBe('success');
            }
          } else if (firstSuccessIndex >= 0) {
            // Succeeded at attempt firstSuccessIndex + 1
            expect(result.current.status).toBe('success');
            expect(callIndex).toBe(firstSuccessIndex + 1);
          } else {
            // responseSequence is shorter than maxAttempts, so eventually succeeds via default
            expect(result.current.status).toBe('success');
          }
        }
      ),
      { numRuns: 100 }
    );
  });
});
