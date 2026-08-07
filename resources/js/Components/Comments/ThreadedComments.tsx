import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { CommentForm } from './CommentForm';
import type { SharedPageProps } from '@/types/index.d';

interface CommentAuthor {
  id: string | null;
  name: string;
}

interface ThreadedComment {
  id: string;
  content_id: string;
  parent_id: string | null;
  body: string;
  is_anonymous: boolean;
  is_edited: boolean;
  depth: number;
  is_accepted_solution: boolean;
  created_at: string;
  edited_at: string | null;
  author: CommentAuthor;
  replies: ThreadedComment[];
}

interface ThreadedCommentsProps {
  /** Content ID these comments belong to */
  contentId: string;
  /** Threaded comment tree from the API */
  comments: ThreadedComment[];
  /** Whether the thread is locked */
  isLocked?: boolean;
  /** Callback to refresh comments after a new one is posted */
  onRefresh?: () => void;
}

function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatRelativeTime(dateString: string): string {
  const date = new Date(dateString);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMins / 60);
  const diffDays = Math.floor(diffHours / 24);

  if (diffMins < 1) return 'just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays < 7) return `${diffDays}d ago`;
  return formatDate(dateString);
}

function CommentItem({
  comment,
  contentId,
  isLocked,
  onRefresh,
}: {
  comment: ThreadedComment;
  contentId: string;
  isLocked: boolean;
  onRefresh?: () => void;
}) {
  const { auth } = usePage<SharedPageProps>().props;
  const [showReplyForm, setShowReplyForm] = useState(false);

  const canReply = auth.user && !isLocked && comment.depth < 5;
  const isAccepted = comment.is_accepted_solution;

  return (
    <div
      className={`${
        comment.depth > 0 ? 'ml-4 pl-4' : ''
      } ${
        isAccepted
          ? 'border-l-2 border-green-500 pl-4'
          : comment.depth > 0
            ? 'border-l-2 border-border'
            : ''
      }`}
    >
      <div className="py-3 space-y-2">
        {/* Accepted solution badge */}
        {isAccepted && (
          <div className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-300">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="12"
              height="12"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
              aria-hidden="true"
            >
              <polyline points="20 6 9 17 4 12" />
            </svg>
            Accepted Solution
          </div>
        )}

        {/* Author and timestamp */}
        <div className="flex items-center gap-2 text-sm">
          {/* Author name - anonymous or real */}
          {comment.is_anonymous || comment.author.id === null ? (
            <span className="font-medium text-muted-foreground">
              Anonymous Member
            </span>
          ) : (
            <span className="font-medium text-foreground">
              {comment.author.name}
            </span>
          )}

          <span className="text-muted-foreground" aria-label="Posted">
            &middot;
          </span>
          <time
            dateTime={comment.created_at}
            className="text-xs text-muted-foreground"
            title={formatDate(comment.created_at)}
          >
            {formatRelativeTime(comment.created_at)}
          </time>

          {/* Edited indicator */}
          {comment.is_edited && (
            <span
              className="text-xs text-muted-foreground italic"
              title={comment.edited_at ? `Edited ${formatDate(comment.edited_at)}` : 'Edited'}
            >
              (edited)
            </span>
          )}
        </div>

        {/* Comment body */}
        <div className="text-sm text-foreground whitespace-pre-wrap break-words">
          {comment.body}
        </div>

        {/* Reply button */}
        {canReply && (
          <button
            type="button"
            onClick={() => setShowReplyForm(!showReplyForm)}
            className="text-xs font-medium text-muted-foreground hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded px-1 py-0.5"
          >
            {showReplyForm ? 'Cancel Reply' : 'Reply'}
          </button>
        )}

        {/* Reply form */}
        {showReplyForm && (
          <div className="mt-2">
            <CommentForm
              contentId={contentId}
              parentId={comment.id}
              isLocked={isLocked}
              placeholder="Write a reply..."
              onSuccess={() => {
                setShowReplyForm(false);
                onRefresh?.();
              }}
              onCancel={() => setShowReplyForm(false)}
            />
          </div>
        )}
      </div>

      {/* Nested replies */}
      {comment.replies && comment.replies.length > 0 && (
        <div className="space-y-0">
          {comment.replies.map((reply) => (
            <CommentItem
              key={reply.id}
              comment={reply}
              contentId={contentId}
              isLocked={isLocked}
              onRefresh={onRefresh}
            />
          ))}
        </div>
      )}
    </div>
  );
}

export function ThreadedComments({
  contentId,
  comments,
  isLocked = false,
  onRefresh,
}: ThreadedCommentsProps) {
  return (
    <section className="space-y-4" aria-label="Comments">
      <h2 className="text-lg font-semibold text-foreground">
        Comments ({comments.length})
      </h2>

      {/* New comment form at the top */}
      <CommentForm
        contentId={contentId}
        isLocked={isLocked}
        onSuccess={onRefresh}
        placeholder="Share your thoughts..."
      />

      {/* Comment list */}
      {comments.length > 0 ? (
        <div className="space-y-0 divide-y divide-border">
          {comments.map((comment) => (
            <CommentItem
              key={comment.id}
              comment={comment}
              contentId={contentId}
              isLocked={isLocked}
              onRefresh={onRefresh}
            />
          ))}
        </div>
      ) : (
        <p className="text-sm text-muted-foreground py-4 text-center">
          No comments yet. Be the first to share your thoughts.
        </p>
      )}
    </section>
  );
}

export default ThreadedComments;
