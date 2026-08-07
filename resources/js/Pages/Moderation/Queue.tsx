import { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/UI/button';
import { Card, CardContent } from '@/Components/UI/card';
import { Input } from '@/Components/UI/input';
import { Label } from '@/Components/UI/label';
import type {
  ContentCategory,
  ModerationAction,
  PaginatedResponse,
  ReportReason,
  SharedPageProps,
} from '@/types/index.d';

interface QueueItem {
  id: string;
  content_id: string;
  content_title: string;
  content_category: ContentCategory;
  author_name: string | null;
  is_anonymous: boolean;
  report_count: number;
  oldest_report_at: string;
  reasons: ReportReason[];
}

interface QueuePageProps extends SharedPageProps {
  queue: PaginatedResponse<QueueItem>;
}

const REASON_LABELS: Record<ReportReason, string> = {
  spam: 'Spam',
  harassment: 'Harassment',
  misinformation: 'Misinformation',
  off_topic: 'Off Topic',
  other: 'Other',
};

const CATEGORY_LABELS: Record<ContentCategory, string> = {
  post_mortem: 'Post-Mortem',
  tech_stack: 'Tech Stack',
  career_interview: 'Career & Interview',
  showcase: 'Showcase',
};

export default function ModerationQueue() {
  const { queue } = usePage<QueuePageProps>().props;
  const [activeReview, setActiveReview] = useState<string | null>(null);

  const goToPage = (page: number) => {
    router.get('/moderation/queue', { page: String(page) }, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  return (
    <AppLayout title="Moderation Queue">
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col gap-4 tablet:flex-row tablet:items-center tablet:justify-between">
          <div>
            <h1 className="text-2xl font-bold text-foreground">
              Moderation Queue
            </h1>
            <p className="mt-1 text-sm text-muted-foreground">
              Flagged content ordered by priority. Items with 3+ reports appear first.
            </p>
          </div>
          <Button
            variant="outline"
            onClick={() => router.visit('/moderation/dashboard')}
            aria-label="Back to dashboard"
          >
            Dashboard
          </Button>
        </div>

        {/* Queue count */}
        <p className="text-sm text-muted-foreground">
          {queue.total} {queue.total === 1 ? 'item' : 'items'} in queue
        </p>

        {/* Queue items */}
        {queue.data.length > 0 ? (
          <div className="space-y-4">
            {queue.data.map((item) => (
              <QueueItemCard
                key={item.id}
                item={item}
                isReviewing={activeReview === item.id}
                onStartReview={() => setActiveReview(item.id)}
                onCancelReview={() => setActiveReview(null)}
                onActionComplete={() => setActiveReview(null)}
              />
            ))}
          </div>
        ) : (
          <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-border py-12 px-4 text-center">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="48"
              height="48"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.5"
              strokeLinecap="round"
              strokeLinejoin="round"
              className="text-muted-foreground/50 mb-4"
              aria-hidden="true"
            >
              <polyline points="20 6 9 17 4 12" />
            </svg>
            <h3 className="text-lg font-medium text-foreground">Queue is empty</h3>
            <p className="mt-1 text-sm text-muted-foreground max-w-sm">
              No flagged content requires review at this time.
            </p>
          </div>
        )}

        {/* Pagination */}
        {queue.last_page > 1 && (
          <nav
            aria-label="Queue pagination"
            className="flex items-center justify-center gap-2 pt-4"
          >
            <Button
              variant="outline"
              size="sm"
              onClick={() => goToPage(queue.current_page - 1)}
              disabled={queue.current_page <= 1}
              aria-label="Previous page"
            >
              Previous
            </Button>
            <span className="text-sm text-muted-foreground px-3">
              Page {queue.current_page} of {queue.last_page}
            </span>
            <Button
              variant="outline"
              size="sm"
              onClick={() => goToPage(queue.current_page + 1)}
              disabled={queue.current_page >= queue.last_page}
              aria-label="Next page"
            >
              Next
            </Button>
          </nav>
        )}
      </div>
    </AppLayout>
  );
}

// Individual queue item card with review actions
interface QueueItemCardProps {
  item: QueueItem;
  isReviewing: boolean;
  onStartReview: () => void;
  onCancelReview: () => void;
  onActionComplete: () => void;
}

function QueueItemCard({
  item,
  isReviewing,
  onStartReview,
  onCancelReview,
  onActionComplete,
}: QueueItemCardProps) {
  const priorityHigh = item.report_count >= 3;

  return (
    <Card className={priorityHigh ? 'border-red-200 dark:border-red-800' : ''}>
      <CardContent className="p-4 space-y-3">
        {/* Item header */}
        <div className="flex flex-col gap-2 tablet:flex-row tablet:items-start tablet:justify-between">
          <div className="space-y-1 flex-1 min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              {priorityHigh && (
                <span className="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-300">
                  High Priority
                </span>
              )}
              <span className="inline-flex items-center rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground">
                {CATEGORY_LABELS[item.content_category]}
              </span>
            </div>
            <h3 className="text-base font-semibold text-foreground truncate">
              {item.content_title}
            </h3>
            <p className="text-sm text-muted-foreground">
              {item.is_anonymous ? 'Anonymous Member' : item.author_name ?? 'Unknown'}
              {' \u00b7 '}
              {item.report_count} {item.report_count === 1 ? 'report' : 'reports'}
              {' \u00b7 '}
              First reported: {new Date(item.oldest_report_at).toLocaleDateString()}
            </p>
          </div>

          {!isReviewing && (
            <Button
              size="sm"
              onClick={onStartReview}
              aria-label={`Review ${item.content_title}`}
            >
              Review
            </Button>
          )}
        </div>

        {/* Report reasons */}
        <div className="flex flex-wrap gap-1.5">
          {item.reasons.map((reason) => (
            <span
              key={reason}
              className="inline-flex items-center rounded-md bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700 dark:bg-orange-900/30 dark:text-orange-300"
            >
              {REASON_LABELS[reason]}
            </span>
          ))}
        </div>

        {/* Review actions panel */}
        {isReviewing && (
          <ReviewActionsPanel
            item={item}
            onCancel={onCancelReview}
            onComplete={onActionComplete}
          />
        )}
      </CardContent>
    </Card>
  );
}

// Review actions panel with remove, dismiss, warn, suspend buttons
interface ReviewActionsPanelProps {
  item: QueueItem;
  onCancel: () => void;
  onComplete: () => void;
}

function ReviewActionsPanel({ item, onCancel, onComplete }: ReviewActionsPanelProps) {
  const [action, setAction] = useState<ModerationAction | null>(null);
  const [processing, setProcessing] = useState(false);

  // Form for suspend action
  const suspendForm = useForm({
    user_id: '',
    days: 7,
    reason: '',
  });

  // Form for warn action
  const warnForm = useForm({
    user_id: '',
    message: '',
  });

  const handleReview = (selectedAction: ModerationAction) => {
    if (selectedAction === 'suspend_user' || selectedAction === 'issue_warning') {
      setAction(selectedAction);
      return;
    }

    setProcessing(true);
    router.post(
      `/moderation/flags/${item.id}`,
      { action: selectedAction },
      {
        preserveScroll: true,
        onSuccess: () => {
          setProcessing(false);
          onComplete();
        },
        onError: () => {
          setProcessing(false);
        },
      }
    );
  };

  const handleSuspend = (e: React.FormEvent) => {
    e.preventDefault();
    setProcessing(true);
    router.post(
      '/moderation/suspend',
      {
        content_id: item.content_id,
        report_id: item.id,
        days: suspendForm.data.days,
        reason: suspendForm.data.reason,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setProcessing(false);
          onComplete();
        },
        onError: () => {
          setProcessing(false);
        },
      }
    );
  };

  const handleWarn = (e: React.FormEvent) => {
    e.preventDefault();
    setProcessing(true);
    router.post(
      '/moderation/warn',
      {
        content_id: item.content_id,
        report_id: item.id,
        message: warnForm.data.message,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setProcessing(false);
          onComplete();
        },
        onError: () => {
          setProcessing(false);
        },
      }
    );
  };

  // Suspend form
  if (action === 'suspend_user') {
    return (
      <div className="border-t border-border pt-3 mt-3 space-y-3">
        <h4 className="text-sm font-medium text-foreground">Suspend User</h4>
        <form onSubmit={handleSuspend} className="space-y-3">
          <div className="grid grid-cols-1 gap-3 tablet:grid-cols-2">
            <div className="space-y-1">
              <Label htmlFor={`suspend-days-${item.id}`} className="text-xs">
                Duration (days)
              </Label>
              <Input
                id={`suspend-days-${item.id}`}
                type="number"
                min={1}
                max={30}
                value={suspendForm.data.days}
                onChange={(e) =>
                  suspendForm.setData('days', parseInt(e.target.value) || 1)
                }
                aria-label="Suspension duration in days"
              />
            </div>
            <div className="space-y-1">
              <Label htmlFor={`suspend-reason-${item.id}`} className="text-xs">
                Reason
              </Label>
              <Input
                id={`suspend-reason-${item.id}`}
                type="text"
                value={suspendForm.data.reason}
                onChange={(e) => suspendForm.setData('reason', e.target.value)}
                placeholder="Reason for suspension"
                required
                aria-label="Suspension reason"
              />
            </div>
          </div>
          <div className="flex gap-2">
            <Button
              type="submit"
              variant="destructive"
              size="sm"
              disabled={processing || !suspendForm.data.reason}
            >
              {processing ? 'Suspending...' : 'Confirm Suspend'}
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => setAction(null)}
              disabled={processing}
            >
              Back
            </Button>
          </div>
        </form>
      </div>
    );
  }

  // Warn form
  if (action === 'issue_warning') {
    return (
      <div className="border-t border-border pt-3 mt-3 space-y-3">
        <h4 className="text-sm font-medium text-foreground">Issue Warning</h4>
        <form onSubmit={handleWarn} className="space-y-3">
          <div className="space-y-1">
            <Label htmlFor={`warn-message-${item.id}`} className="text-xs">
              Warning Message
            </Label>
            <Input
              id={`warn-message-${item.id}`}
              type="text"
              value={warnForm.data.message}
              onChange={(e) => warnForm.setData('message', e.target.value)}
              placeholder="Describe the violation and expected behavior"
              required
              aria-label="Warning message"
            />
          </div>
          <div className="flex gap-2">
            <Button
              type="submit"
              variant="default"
              size="sm"
              disabled={processing || !warnForm.data.message}
              className="bg-orange-600 hover:bg-orange-700 text-white"
            >
              {processing ? 'Sending...' : 'Send Warning'}
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => setAction(null)}
              disabled={processing}
            >
              Back
            </Button>
          </div>
        </form>
      </div>
    );
  }

  // Default action buttons
  return (
    <div className="border-t border-border pt-3 mt-3">
      <p className="text-xs text-muted-foreground mb-2">Choose a moderation action:</p>
      <div className="flex flex-wrap gap-2">
        <Button
          variant="destructive"
          size="sm"
          onClick={() => handleReview('remove_content')}
          disabled={processing}
          aria-label="Remove content"
        >
          {processing ? 'Processing...' : 'Remove'}
        </Button>
        <Button
          size="sm"
          onClick={() => handleReview('issue_warning')}
          disabled={processing}
          className="bg-orange-600 hover:bg-orange-700 text-white"
          aria-label="Issue warning to author"
        >
          Warn
        </Button>
        <Button
          variant="destructive"
          size="sm"
          onClick={() => handleReview('suspend_user')}
          disabled={processing}
          aria-label="Suspend user"
        >
          Suspend
        </Button>
        <Button
          variant="secondary"
          size="sm"
          onClick={() => handleReview('dismiss')}
          disabled={processing}
          aria-label="Dismiss report"
        >
          Dismiss
        </Button>
        <Button
          variant="ghost"
          size="sm"
          onClick={onCancel}
          disabled={processing}
          aria-label="Cancel review"
        >
          Cancel
        </Button>
      </div>
    </div>
  );
}
