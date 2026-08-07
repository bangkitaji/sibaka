import { useCallback, useEffect, useState } from 'react';
import { router } from '@inertiajs/react';

interface UseUnsavedChangesOptions {
  /** Whether there are unsaved changes */
  hasUnsavedChanges: boolean;
  /** Callback to save changes before navigating */
  onSave: () => Promise<void>;
  /** Whether the guard is enabled (default: true) */
  enabled?: boolean;
}

interface UseUnsavedChangesReturn {
  /** Whether the modal is visible */
  showModal: boolean;
  /** Confirm save and continue navigation */
  confirmSave: () => void;
  /** Discard changes and continue navigation */
  confirmDiscard: () => void;
  /** Cancel and stay on the page */
  cancelNavigation: () => void;
}

export function useUnsavedChanges({
  hasUnsavedChanges,
  onSave,
  enabled = true,
}: UseUnsavedChangesOptions): UseUnsavedChangesReturn {
  const [showModal, setShowModal] = useState(false);
  const [pendingNavigation, setPendingNavigation] = useState<(() => void) | null>(null);

  // Browser beforeunload event - handles tab close, browser close, refresh
  useEffect(() => {
    if (!enabled || !hasUnsavedChanges) return;

    const handleBeforeUnload = (e: BeforeUnloadEvent) => {
      e.preventDefault();
      // Modern browsers show their own message, returnValue is deprecated but needed for some browsers
      e.returnValue = '';
    };

    window.addEventListener('beforeunload', handleBeforeUnload);
    return () => {
      window.removeEventListener('beforeunload', handleBeforeUnload);
    };
  }, [enabled, hasUnsavedChanges]);

  // Inertia navigation interception
  useEffect(() => {
    if (!enabled || !hasUnsavedChanges) return;

    const removeListener = router.on('before', (event) => {
      // Allow the navigation if modal was already confirmed
      if (!hasUnsavedChanges) return true;

      // Show modal and block navigation
      setShowModal(true);
      setPendingNavigation(() => () => {
        // Re-visit the intended URL after confirmation
        const { url } = event.detail.visit;
        router.visit(url);
      });

      return false;
    });

    return () => {
      removeListener();
    };
  }, [enabled, hasUnsavedChanges]);

  const confirmSave = useCallback(async () => {
    try {
      await onSave();
    } catch {
      // Even if save fails, allow navigation
    }
    setShowModal(false);
    if (pendingNavigation) {
      pendingNavigation();
      setPendingNavigation(null);
    }
  }, [onSave, pendingNavigation]);

  const confirmDiscard = useCallback(() => {
    setShowModal(false);
    if (pendingNavigation) {
      pendingNavigation();
      setPendingNavigation(null);
    }
  }, [pendingNavigation]);

  const cancelNavigation = useCallback(() => {
    setShowModal(false);
    setPendingNavigation(null);
  }, []);

  return {
    showModal,
    confirmSave,
    confirmDiscard,
    cancelNavigation,
  };
}
