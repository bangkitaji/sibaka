import { type FormEventHandler, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/UI/button';
import { Label } from '@/Components/UI/label';

interface CommentFormProps {
  /** Content ID to comment on */
  contentId: string;
  /** Optional parent comment ID for replies */
  parentId?: string | null;
  /** Whether the thread is locked */
  isLocked?: boolean;
  /** Callback when comment is successfully submitted */
  onSuccess?: () => void;
  /** Callback to cancel (e.g., close reply form) */
  onCancel?: () => void;
  /** Placeholder text */
  placeholder?: string;
}

export function CommentForm({
  contentId,
  parentId = null,
  isLocked = false,
  onSuccess,
  onCancel,
  placeholder = 'Write a comment...',
}: CommentFormProps) {
  const { data, setData, post, processing, errors, reset } = useForm({
    text: '',
    parent_id: parentId,
    is_anonymous: false,
  });

  const [charCount, setCharCount] = useState(0);
  const MAX_CHARS = 5000;

  const handleTextChange = (value: string) => {
    setData('text', value);
    setCharCount(value.trim().length);
  };

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    post(`/content/${contentId}/comments`, {
      preserveScroll: true,
      onSuccess: () => {
        reset();
        setCharCount(0);
        onSuccess?.();
      },
    });
  };

  if (isLocked) {
    return (
      <div
        className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300"
        role="alert"
      >
        <div className="flex items-center gap-2">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
          >
            <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
          </svg>
          <span>Thread locked due to inactivity</span>
        </div>
      </div>
    );
  }

  return (
    <form onSubmit={submit} className="space-y-3" noValidate>
      {/* Comment text area */}
      <div className="space-y-1">
        <Label htmlFor={`comment-text-${parentId ?? 'root'}`} className="sr-only">
          {parentId ? 'Reply' : 'Comment'}
        </Label>
        <textarea
          id={`comment-text-${parentId ?? 'root'}`}
          value={data.text}
          onChange={(e) => handleTextChange(e.target.value)}
          placeholder={placeholder}
          rows={3}
          maxLength={MAX_CHARS}
          className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 resize-y min-h-[80px]"
          aria-invalid={!!errors.text}
          aria-describedby={errors.text ? `comment-error-${parentId ?? 'root'}` : undefined}
          disabled={processing}
        />
        <div className="flex items-center justify-between">
          {errors.text && (
            <p
              id={`comment-error-${parentId ?? 'root'}`}
              className="text-sm text-destructive"
              role="alert"
            >
              {errors.text}
            </p>
          )}
          <span
            className={`ml-auto text-xs ${
              charCount > MAX_CHARS * 0.9
                ? 'text-destructive'
                : 'text-muted-foreground'
            }`}
            aria-live="polite"
          >
            {charCount}/{MAX_CHARS}
          </span>
        </div>
      </div>

      {/* Options row: anonymous toggle + actions */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        {/* Anonymous toggle */}
        <div className="flex items-center gap-2">
          <input
            id={`comment-anonymous-${parentId ?? 'root'}`}
            type="checkbox"
            checked={data.is_anonymous}
            onChange={(e) => setData('is_anonymous', e.target.checked)}
            className="h-4 w-4 rounded border-input text-primary focus:ring-ring"
            disabled={processing}
          />
          <Label
            htmlFor={`comment-anonymous-${parentId ?? 'root'}`}
            className="cursor-pointer text-sm font-normal text-muted-foreground"
          >
            Post anonymously
          </Label>
        </div>

        {/* Action buttons */}
        <div className="flex items-center gap-2">
          {onCancel && (
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={onCancel}
              disabled={processing}
            >
              Cancel
            </Button>
          )}
          <Button
            type="submit"
            size="sm"
            disabled={processing || charCount === 0}
            aria-busy={processing}
          >
            {processing ? 'Posting...' : parentId ? 'Reply' : 'Comment'}
          </Button>
        </div>
      </div>
    </form>
  );
}

export default CommentForm;
