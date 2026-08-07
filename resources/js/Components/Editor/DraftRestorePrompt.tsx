import { Button } from '@/Components/UI/button';
import { cn } from '@/lib/utils';

interface DraftRestorePromptProps {
  isVisible: boolean;
  isLoading?: boolean;
  onRestore: () => void;
  onDismiss: () => void;
  className?: string;
}

export function DraftRestorePrompt({
  isVisible,
  isLoading = false,
  onRestore,
  onDismiss,
  className,
}: DraftRestorePromptProps) {
  if (!isVisible) return null;

  if (isLoading) {
    return (
      <div
        className={cn(
          'rounded-md border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950',
          className
        )}
        role="status"
        aria-label="Checking for saved drafts"
      >
        <p className="text-sm text-blue-700 dark:text-blue-300">
          Checking for saved drafts...
        </p>
      </div>
    );
  }

  return (
    <div
      className={cn(
        'rounded-md border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950',
        className
      )}
      role="alert"
      aria-labelledby="draft-restore-title"
    >
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h3
            id="draft-restore-title"
            className="text-sm font-medium text-amber-800 dark:text-amber-200"
          >
            Unsaved Draft Found
          </h3>
          <p className="mt-1 text-sm text-amber-700 dark:text-amber-300">
            A previously saved draft was found. Would you like to restore it?
          </p>
        </div>
        <div className="flex gap-2 shrink-0">
          <Button
            variant="ghost"
            size="sm"
            onClick={onDismiss}
            aria-label="Dismiss draft and start fresh"
          >
            Dismiss
          </Button>
          <Button
            variant="default"
            size="sm"
            onClick={onRestore}
            aria-label="Restore saved draft"
          >
            Restore Draft
          </Button>
        </div>
      </div>
    </div>
  );
}

export default DraftRestorePrompt;
