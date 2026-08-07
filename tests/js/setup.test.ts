import { describe, it, expect } from 'vitest';
import * as fc from 'fast-check';

describe('Frontend Testing Setup', () => {
  it('should run vitest correctly', () => {
    expect(1 + 1).toBe(2);
  });

  it('should have fast-check available for property-based testing', () => {
    fc.assert(
      fc.property(fc.integer(), fc.integer(), (a, b) => {
        return a + b === b + a;
      }),
      { numRuns: 100 }
    );
  });
});
