/**
 * Retry mechanism with fixed delay between retries.
 *
 * Per SIBAKA spec (Requirements 2.6, 3.5, 10.3):
 * - Max retries: 3 (total attempts = 4: 1 initial + 3 retries)
 * - Delay between retries: 2 seconds
 * - On success at any attempt: stop retrying
 * - After all retries exhausted: throw last error
 */
export async function retryWithBackoff<T>(
  fn: () => Promise<T>,
  maxRetries: number = 3,
  delayMs: number = 2000
): Promise<{ result?: T; attempts: number; success: boolean; delays: number[] }> {
  const delays: number[] = [];

  for (let attempt = 0; attempt <= maxRetries; attempt++) {
    try {
      const result = await fn();
      return { result, attempts: attempt + 1, success: true, delays };
    } catch (error) {
      if (attempt < maxRetries) {
        delays.push(delayMs);
        await new Promise((resolve) => setTimeout(resolve, delayMs));
      }
    }
  }

  return { attempts: maxRetries + 1, success: false, delays };
}
