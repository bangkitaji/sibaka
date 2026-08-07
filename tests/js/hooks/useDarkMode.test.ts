import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useDarkMode } from '../../../resources/js/Hooks/useDarkMode';

/**
 * Unit tests for useDarkMode hook
 * Tests system preference detection, localStorage persistence, toggle, and class application.
 *
 * Validates: Requirements 10.5
 */

describe('useDarkMode', () => {
  let matchMediaListeners: Map<string, ((e: MediaQueryListEvent) => void)[]>;
  let matchMediaMatches: boolean;

  beforeEach(() => {
    // Reset localStorage
    localStorage.clear();

    // Reset document class
    document.documentElement.classList.remove('dark');

    // Mock matchMedia
    matchMediaListeners = new Map();
    matchMediaMatches = false;

    Object.defineProperty(window, 'matchMedia', {
      writable: true,
      value: vi.fn().mockImplementation((query: string) => ({
        matches: matchMediaMatches,
        media: query,
        onchange: null,
        addEventListener: vi.fn((event: string, handler: (e: MediaQueryListEvent) => void) => {
          const key = `${query}:${event}`;
          if (!matchMediaListeners.has(key)) {
            matchMediaListeners.set(key, []);
          }
          matchMediaListeners.get(key)!.push(handler);
        }),
        removeEventListener: vi.fn((event: string, handler: (e: MediaQueryListEvent) => void) => {
          const key = `${query}:${event}`;
          const listeners = matchMediaListeners.get(key) || [];
          const index = listeners.indexOf(handler);
          if (index > -1) listeners.splice(index, 1);
        }),
        dispatchEvent: vi.fn(),
      })),
    });
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('defaults to system preference (light)', () => {
    matchMediaMatches = false;

    const { result } = renderHook(() => useDarkMode());

    expect(result.current.isDark).toBe(false);
    expect(result.current.preference).toBe('system');
    expect(document.documentElement.classList.contains('dark')).toBe(false);
  });

  it('defaults to system preference (dark)', () => {
    matchMediaMatches = true;

    const { result } = renderHook(() => useDarkMode());

    expect(result.current.isDark).toBe(true);
    expect(result.current.preference).toBe('system');
    expect(document.documentElement.classList.contains('dark')).toBe(true);
  });

  it('reads stored preference from localStorage', () => {
    localStorage.setItem('dark-mode-preference', 'dark');
    matchMediaMatches = false;

    const { result } = renderHook(() => useDarkMode());

    expect(result.current.isDark).toBe(true);
    expect(result.current.preference).toBe('dark');
    expect(document.documentElement.classList.contains('dark')).toBe(true);
  });

  it('respects stored light preference even when system is dark', () => {
    localStorage.setItem('dark-mode-preference', 'light');
    matchMediaMatches = true;

    const { result } = renderHook(() => useDarkMode());

    expect(result.current.isDark).toBe(false);
    expect(result.current.preference).toBe('light');
    expect(document.documentElement.classList.contains('dark')).toBe(false);
  });

  it('toggle switches from light to dark', () => {
    matchMediaMatches = false;

    const { result } = renderHook(() => useDarkMode());

    expect(result.current.isDark).toBe(false);

    act(() => {
      result.current.toggle();
    });

    expect(result.current.isDark).toBe(true);
    expect(result.current.preference).toBe('dark');
    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(localStorage.getItem('dark-mode-preference')).toBe('dark');
  });

  it('toggle switches from dark to light', () => {
    matchMediaMatches = true;

    const { result } = renderHook(() => useDarkMode());

    expect(result.current.isDark).toBe(true);

    act(() => {
      result.current.toggle();
    });

    expect(result.current.isDark).toBe(false);
    expect(result.current.preference).toBe('light');
    expect(document.documentElement.classList.contains('dark')).toBe(false);
    expect(localStorage.getItem('dark-mode-preference')).toBe('light');
  });

  it('setMode allows explicit mode setting', () => {
    matchMediaMatches = false;

    const { result } = renderHook(() => useDarkMode());

    act(() => {
      result.current.setMode('dark');
    });

    expect(result.current.isDark).toBe(true);
    expect(result.current.preference).toBe('dark');
    expect(localStorage.getItem('dark-mode-preference')).toBe('dark');

    act(() => {
      result.current.setMode('system');
    });

    expect(result.current.isDark).toBe(false); // system is light
    expect(result.current.preference).toBe('system');
    expect(localStorage.getItem('dark-mode-preference')).toBe('system');
  });

  it('responds to system preference changes when in system mode', () => {
    matchMediaMatches = false;

    const { result } = renderHook(() => useDarkMode());

    expect(result.current.isDark).toBe(false);

    // Simulate system preference changing to dark
    act(() => {
      const key = '(prefers-color-scheme: dark):change';
      const listeners = matchMediaListeners.get(key) || [];
      listeners.forEach((listener) => {
        listener({ matches: true } as MediaQueryListEvent);
      });
    });

    expect(result.current.isDark).toBe(true);
    expect(document.documentElement.classList.contains('dark')).toBe(true);
  });

  it('ignores system preference changes when user has explicit preference', () => {
    localStorage.setItem('dark-mode-preference', 'light');
    matchMediaMatches = false;

    const { result } = renderHook(() => useDarkMode());

    expect(result.current.isDark).toBe(false);

    // Simulate system preference changing to dark
    act(() => {
      const key = '(prefers-color-scheme: dark):change';
      const listeners = matchMediaListeners.get(key) || [];
      listeners.forEach((listener) => {
        listener({ matches: true } as MediaQueryListEvent);
      });
    });

    // Should still be light because user explicitly chose light
    expect(result.current.isDark).toBe(false);
    expect(document.documentElement.classList.contains('dark')).toBe(false);
  });

  it('persists preference across re-renders', () => {
    matchMediaMatches = false;

    const { result, rerender } = renderHook(() => useDarkMode());

    act(() => {
      result.current.toggle();
    });

    expect(result.current.isDark).toBe(true);

    rerender();

    expect(result.current.isDark).toBe(true);
    expect(result.current.preference).toBe('dark');
  });

  it('handles invalid localStorage values gracefully', () => {
    localStorage.setItem('dark-mode-preference', 'invalid-value');
    matchMediaMatches = false;

    const { result } = renderHook(() => useDarkMode());

    // Should fall back to 'system'
    expect(result.current.preference).toBe('system');
    expect(result.current.isDark).toBe(false);
  });
});
