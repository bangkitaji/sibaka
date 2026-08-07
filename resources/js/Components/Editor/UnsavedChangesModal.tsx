import { Button } from '@/Components/UI/button';
import { cn } from '@/lib/utils';

interface UnsavedChangesModalProps {
  isOpen: boolean;
  onSave: () => void;
  onDiscard: () => void;
  onCancel: () => void;
}

export function UnsavedChangesModal({
  isOpen,
  onSave,
  onDiscard,
  onCancel,
}: UnsavedChangesModalProps) {
  if (!isOpen) return null;

  return (
    <div
      className="fixed inset-0 z-[100] flex items-center justify-center"
      role="dialog"
      aria-modal="true"
      aria-labelledby="unsaved-changes-title"
      aria-describedby="unsaved-changes-description"
    >
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black/50 dark:bg-black/70"
        onClick={onCancel}
        aria-hidden="true"
      />

      {/* Modal content */}
      <div
        className={cn(
          'relative z-10 w-full max-w-md rounded-lg bg-white p-6 shadow-xl',
          'dark:bg-gray-800 dark:border dark:border-gray-700'
        )}
      >
        <h2
          id="unsaved-changes-title"
          className="text-lg font-semibold text-gray-900 dark:text-gray-100"
        >
          Unsaved Changes
        </h2>
        <p
          id="unsaved-changes-description"
          className="mt-2 text-sm text-gray-600 dark:text-gray-400"
        >
          You have unsaved changes. Would you like to save them before leaving?
        </p>

        <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <Button
            variant="ghost"
            onClick={onCancel}
            aria-label="Cancel navigation and stay on page"
          >
            Cancel
          </Button>
          <Button
            variant="destructive"
            onClick={onDiscard}
            aria-label="Discard changes and leave page"
          >
            Discard
          </Button>
          <Button
            variant="default"
            onClick={onSave}
            aria-label="Save changes and leave page"
          >
            Save & Leave
          </Button>
        </div>
      </div>
    </div>
  );
}

export default UnsavedChangesModal;
