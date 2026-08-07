import { useEffect, useState, useCallback } from 'react';

const STORAGE_KEY = 'dark-mode-preference';

type DarkModePreference = 'light' | 'dark' | 'system';

function getSystemPreference(): boolean {
  if (typeof window === 'undefined') return false;
  return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function getStoredPreference(): DarkModePreference {
  if (typeof window === 'undefined') return 'system';
  const stored = localStorage.getItem(STORAGE_KEY);
  if (stored === 'light' || stored === 'dark' || stored === 'system') {
    return stored;
  }
  return 'system';
}

function resolveIsDark(preference: DarkModePreference): boolean {
  if (preference === 'system') {
    return getSystemPreference();
  }
  return preference === 'dark';
}

export function useDarkMode() {
  const [preference, setPreference] = useState<DarkModePreference>(getStoredPreference);
  const [isDark, setIsDark] = useState<boolean>(() => resolveIsDark(getStoredPreference()));

  // Apply dark class to document element
  useEffect(() => {
    const root = document.documentElement;
    if (isDark) {
      root.classList.add('dark');
    } else {
      root.classList.remove('dark');
    }
  }, [isDark]);

  // Listen for system preference changes
  useEffect(() => {
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

    const handleChange = (e: MediaQueryListEvent) => {
      if (preference === 'system') {
        setIsDark(e.matches);
      }
    };

    mediaQuery.addEventListener('change', handleChange);
    return () => mediaQuery.removeEventListener('change', handleChange);
  }, [preference]);

  const setMode = useCallback((mode: DarkModePreference) => {
    setPreference(mode);
    localStorage.setItem(STORAGE_KEY, mode);
    setIsDark(resolveIsDark(mode));
  }, []);

  const toggle = useCallback(() => {
    const newMode: DarkModePreference = isDark ? 'light' : 'dark';
    setMode(newMode);
  }, [isDark, setMode]);

  return {
    isDark,
    preference,
    setMode,
    toggle,
  };
}
