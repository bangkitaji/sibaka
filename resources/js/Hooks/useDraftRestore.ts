import { useCallback, useEffect, useState } from 'react';

interface UseDraftRestoreOptions {
  /** Content ID to check for saved draft */
  contentId: string;
  /** Whether restoration is enabled (default: true) */
  enabled?: boolean;
}

interface UseDraftRestoreReturn {
  /** The restored draft body, or null if none available */
  draftBody: string | null;
  /** Whether a draft is available for restoration */
  hasDraft: boolean;
  /** Whether the draft is currently being loaded */
  isLoading: boolean;
  /** Accept the restored draft (clear the offer) */
  acceptDraft: () => string | null;
  /** Dismiss the draft offer */
  dismissDraft: () => void;
}

async function fetchDraft(contentId: string): Promise<{ body: string | null; has_draft: boolean }> {
  const csrfToken = document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute('content');

  const response = await fetch(`/content/${contentId}/draft`, {
    method: 'GET',
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
    },
    credentials: 'same-origin',
  });

  if (!response.ok) {
    throw new Error(`Draft fetch failed: ${response.status}`);
  }

  return response.json();
}

export function useDraftRestore({
  contentId,
  enabled = true,
}: UseDraftRestoreOptions): UseDraftRestoreReturn {
  const [draftBody, setDraftBody] = useState<string | null>(null);
  const [hasDraft, setHasDraft] = useState(false);
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    if (!enabled || !contentId) return;

    let cancelled = false;
    setIsLoading(true);

    fetchDraft(contentId)
      .then((data) => {
        if (cancelled) return;
        if (data.has_draft && data.body) {
          setDraftBody(data.body);
          setHasDraft(true);
        } else {
          setDraftBody(null);
          setHasDraft(false);
        }
      })
      .catch(() => {
        if (cancelled) return;
        setDraftBody(null);
        setHasDraft(false);
      })
      .finally(() => {
        if (!cancelled) {
          setIsLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [contentId, enabled]);

  const acceptDraft = useCallback((): string | null => {
    const body = draftBody;
    setHasDraft(false);
    return body;
  }, [draftBody]);

  const dismissDraft = useCallback(() => {
    setDraftBody(null);
    setHasDraft(false);
  }, []);

  return {
    draftBody,
    hasDraft,
    isLoading,
    acceptDraft,
    dismissDraft,
  };
}
