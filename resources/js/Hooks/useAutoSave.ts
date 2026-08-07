import { useCallback, useEffect, useRef, useState } from 'react';

export type AutoSaveStatus = 'idle' | 'saving' | 'saved' | 'failed';

interface UseAutoSaveOptions {
  /** Content ID to save draft for */
  contentId: string;
  /** Current editor content */
  content: string;
  /** Auto-save interval in milliseconds (default: 10000) */
  interval?: number;
  /** Number of retries on failure (default: 3) */
  maxRetries?: number;
  /** Delay between retries in milliseconds (default: 2000) */
  retryDelay?: number;
  /** Whether auto-save is enabled (default: true) */
  enabled?: boolean;
}

interface UseAutoSaveReturn {
  /** Current save status */
  status: AutoSaveStatus;
  /** Timestamp of last successful save */
  lastSaved: Date | null;
  /** Manually trigger a save */
  save: () => Promise<void>;
  /** Whether there are unsaved changes since last successful save */
  hasUnsavedChanges: boolean;
}

async function saveDraft(contentId: string, body: string): Promise<void> {
  const csrfToken = document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute('content');

  const response = await fetch(`/content/${contentId}/draft`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
    },
    credentials: 'same-origin',
    body: JSON.stringify({ body }),
  });

  if (!response.ok) {
    throw new Error(`Draft save failed: ${response.status}`);
  }
}

function delay(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

export function useAutoSave({
  contentId,
  content,
  interval = 10_000,
  maxRetries = 3,
  retryDelay = 2_000,
  enabled = true,
}: UseAutoSaveOptions): UseAutoSaveReturn {
  const [status, setStatus] = useState<AutoSaveStatus>('idle');
  const [lastSaved, setLastSaved] = useState<Date | null>(null);
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);

  const lastSavedContentRef = useRef<string>(content);
  const contentRef = useRef<string>(content);
  const isSavingRef = useRef(false);
  const savedTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

  // Keep content ref updated
  useEffect(() => {
    contentRef.current = content;

    // Mark unsaved changes if content differs from last saved
    if (content !== lastSavedContentRef.current) {
      setHasUnsavedChanges(true);
    }
  }, [content]);

  const performSave = useCallback(async () => {
    const currentContent = contentRef.current;

    // Skip if no changes since last save
    if (currentContent === lastSavedContentRef.current) {
      return;
    }

    // Skip if already saving
    if (isSavingRef.current) {
      return;
    }

    isSavingRef.current = true;
    setStatus('saving');

    // Clear any existing "saved" timer
    if (savedTimerRef.current) {
      clearTimeout(savedTimerRef.current);
      savedTimerRef.current = null;
    }

    let attempts = 0;
    let success = false;

    while (attempts <= maxRetries && !success) {
      try {
        await saveDraft(contentId, currentContent);
        success = true;
      } catch {
        attempts++;
        if (attempts <= maxRetries) {
          await delay(retryDelay);
        }
      }
    }

    isSavingRef.current = false;

    if (success) {
      lastSavedContentRef.current = currentContent;
      setLastSaved(new Date());
      setHasUnsavedChanges(false);
      setStatus('saved');

      // Return to idle after 2 seconds
      savedTimerRef.current = setTimeout(() => {
        setStatus('idle');
        savedTimerRef.current = null;
      }, 2_000);
    } else {
      setStatus('failed');
    }
  }, [contentId, maxRetries, retryDelay]);

  // Set up auto-save interval
  useEffect(() => {
    if (!enabled) return;

    intervalRef.current = setInterval(() => {
      void performSave();
    }, interval);

    return () => {
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
        intervalRef.current = null;
      }
    };
  }, [enabled, interval, performSave]);

  // Cleanup saved timer on unmount
  useEffect(() => {
    return () => {
      if (savedTimerRef.current) {
        clearTimeout(savedTimerRef.current);
      }
    };
  }, []);

  // Manual save trigger
  const save = useCallback(async () => {
    await performSave();
  }, [performSave]);

  return {
    status,
    lastSaved,
    save,
    hasUnsavedChanges,
  };
}
