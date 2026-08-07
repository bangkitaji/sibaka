import { cn } from '@/lib/utils';
import type { AutoSaveStatus } from '@/Hooks/useAutoSave';

interface AutoSaveStatusIndicatorProps {
  status: AutoSaveStatus;
  lastSaved: Date | null;
  className?: string;
}

export function AutoSaveStatusIndicator({
  status,
  lastSaved,
  className,
}: AutoSaveStatusIndicatorProps) {
  if (status === 'idle' && !lastSaved) {
    return null;
  }

  return (
    <div
      className={cn(
        'fixed bottom-4 right-4 z-50 flex items-center gap-2 rounded-md px-3 py-2 text-sm shadow-md transition-all',
        'dark:shadow-gray-900/50',
        status === 'saving' && 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
        status === 'saved' && 'bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300',
        status === 'failed' && 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
        status === 'idle' && 'bg-gray-50 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
        className
      )}
      role="status"
      aria-live="polite"
      aria-atomic="true"
    >
      {status === 'saving' && (
        <>
          <SavingSpinner />
          <span>Saving...</span>
        </>
      )}
      {status === 'saved' && (
        <>
          <CheckIcon />
          <span>Saved</span>
        </>
      )}
      {status === 'failed' && (
        <>
          <ErrorIcon />
          <span>Save failed</span>
        </>
      )}
      {status === 'idle' && lastSaved && (
        <span className="text-xs">
          Last saved {formatTime(lastSaved)}
        </span>
      )}
    </div>
  );
}

function SavingSpinner() {
  return (
    <svg
      className="h-4 w-4 animate-spin"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
      aria-hidden="true"
    >
      <circle
        className="opacity-25"
        cx="12"
        cy="12"
        r="10"
        stroke="currentColor"
        strokeWidth="4"
      />
      <path
        className="opacity-75"
        fill="currentColor"
        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
      />
    </svg>
  );
}

function CheckIcon() {
  return (
    <svg
      className="h-4 w-4"
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M20 6L9 17l-5-5" />
    </svg>
  );
}

function ErrorIcon() {
  return (
    <svg
      className="h-4 w-4"
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <circle cx="12" cy="12" r="10" />
      <line x1="15" y1="9" x2="9" y2="15" />
      <line x1="9" y1="9" x2="15" y2="15" />
    </svg>
  );
}

function formatTime(date: Date): string {
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffSecs = Math.floor(diffMs / 1000);

  if (diffSecs < 60) return 'just now';
  if (diffSecs < 3600) return `${Math.floor(diffSecs / 60)}m ago`;
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

export default AutoSaveStatusIndicator;
