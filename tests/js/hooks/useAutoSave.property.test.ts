import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import * as fc from 'fast-check';
import { retryWithBackoff } from '../../../resources/js/lib/retry';

/**
 * Property 5: Retry Mechanism Correctness
 *
 * **Validates: Requirements 2.6, 3.5, 10.3**
 *
 * Tests that:
 * 1. If the first attempt succeeds (K=0 failures), total attempts = 1
 * 2. If the first K attempts fail and attempt K+1 succeeds, total attempts = K+1 (max K=3)
 * 3. If all 4 attempts (1 initial + 3 retries) fail, total attempts = 4
 * 4. Retry delays follow fixed 2-second intervals between retries
 * 5. For any random number of consecutive failures K, total attempts = min(K+1, 4)
 */
describe('Property 5: Retry Mechanism Correctness', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  /**
   * Helper: creates a function that fails `failCount` times then succeeds.
   */
  function createMockFn(failCount: number) {
    let callCount = 0;
    return {
      fn: () => {
        callCount++;
        if (callCount <= failCount) {
          return Promise.reject(new Error(`Failure #${callCount}`));
        }
        return Promise.resolve('success');
      },
      getCallCount: () => callCount,
    };
  }

  /**
   * Helper: runs retryWithBackoff while advancing fake timers to resolve delays.
   */
  async function runRetryWithTimers(failCount: number, maxRetries = 3, delayMs = 2000) {
    const mock = createMockFn(failCount);
    const resultPromise = retryWithBackoff(mock.fn, maxRetries, delayMs);

    // Advance timers to resolve all pending delays.
    // Each retry introduces a delay, so we advance enough times.
    for (let i = 0; i < maxRetries; i++) {
      await vi.advanceTimersByTimeAsync(delayMs);
    }

    const result = await resultPromise;
    return { result, callCount: mock.getCallCount() };
  }

  it('Property 5.1: If first attempt succeeds (K=0 failures), total attempts = 1', async () => {
    await fc.assert(
      fc.asyncProperty(fc.constant(0), async (_) => {
        const { result } = await runRetryWithTimers(0);
        expect(result.attempts).toBe(1);
        expect(result.success).toBe(true);
        expect(result.delays).toHaveLength(0);
      }),
      { numRuns: 10 }
    );
  });

  it('Property 5.2: If first K attempts fail and attempt K+1 succeeds, total attempts = K+1 (max K=3)', async () => {
    await fc.assert(
      fc.asyncProperty(fc.integer({ min: 1, max: 3 }), async (k) => {
        const { result } = await runRetryWithTimers(k);
        expect(result.attempts).toBe(k + 1);
        expect(result.success).toBe(true);
        expect(result.delays).toHaveLength(k);
      }),
      { numRuns: 50 }
    );
  });

  it('Property 5.3: If all 4 attempts fail, total attempts = 4 and success = false', async () => {
    await fc.assert(
      fc.asyncProperty(fc.integer({ min: 4, max: 100 }), async (failCount) => {
        const { result } = await runRetryWithTimers(failCount);
        expect(result.attempts).toBe(4);
        expect(result.success).toBe(false);
        expect(result.delays).toHaveLength(3);
      }),
      { numRuns: 50 }
    );
  });

  it('Property 5.4: Retry delays are all 2 seconds (fixed backoff per spec)', async () => {
    await fc.assert(
      fc.asyncProperty(fc.integer({ min: 1, max: 3 }), async (k) => {
        const { result } = await runRetryWithTimers(k, 3, 2000);
        // Each delay should be exactly 2000ms
        for (const delay of result.delays) {
          expect(delay).toBe(2000);
        }
        // Number of delays equals number of retries performed
        expect(result.delays).toHaveLength(k);
      }),
      { numRuns: 50 }
    );
  });

  it('Property 5.5: For any random consecutive failures K, total attempts = min(K+1, 4)', async () => {
    await fc.assert(
      fc.asyncProperty(fc.nat({ max: 20 }), async (k) => {
        const { result } = await runRetryWithTimers(k);
        const expectedAttempts = Math.min(k + 1, 4);
        expect(result.attempts).toBe(expectedAttempts);

        if (k < 4) {
          expect(result.success).toBe(true);
        } else {
          expect(result.success).toBe(false);
        }

        // Delays count = min(k, 3)
        const expectedDelays = Math.min(k, 3);
        expect(result.delays).toHaveLength(expectedDelays);
      }),
      { numRuns: 100 }
    );
  });
});
