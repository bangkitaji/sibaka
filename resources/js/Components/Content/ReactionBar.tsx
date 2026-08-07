import { cn } from '@/lib/utils';
import { useOptimisticReaction } from '@/Hooks/useOptimisticReaction';
import type { ReactionSummary, ReactionType } from '@/types/index.d';

interface ReactionBarProps {
  contentId: string;
  initialSummary: ReactionSummary;
  /** Whether the user is authenticated (reactions require auth) */
  isAuthenticated?: boolean;
}

interface ReactionButtonConfig {
  type: ReactionType;
  label: string;
  icon: React.ReactNode;
}

const REACTION_BUTTONS: ReactionButtonConfig[] = [
  {
    type: 'insightful',
    label: 'Insightful',
    icon: (
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="18"
        height="18"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
        aria-hidden="true"
      >
        <circle cx="12" cy="12" r="10" />
        <path d="M12 16v-4" />
        <path d="M12 8h.01" />
      </svg>
    ),
  },
  {
    type: 'relatable',
    label: 'Relatable',
    icon: (
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="18"
        height="18"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
        aria-hidden="true"
      >
        <path d="M17 6.1H3" />
        <path d="M21 12.1H3" />
        <path d="M15.1 18H3" />
      </svg>
    ),
  },
  {
    type: 'helpful',
    label: 'Helpful',
    icon: (
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="18"
        height="18"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
        aria-hidden="true"
      >
        <path d="M7 10v12" />
        <path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2h0a3.13 3.13 0 0 1 3 3.88Z" />
      </svg>
    ),
  },
  {
    type: 'solutif',
    label: 'Solutif',
    icon: (
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="18"
        height="18"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
        aria-hidden="true"
      >
        <path d="M12 2v4" />
        <path d="m6.8 15-3.5 2" />
        <path d="m20.7 17-3.5-2" />
        <path d="M6.8 9 3.3 7" />
        <path d="m20.7 7-3.5 2" />
        <circle cx="12" cy="12" r="4" />
      </svg>
    ),
  },
];

/**
 * ReactionBar displays 4 reaction buttons (Insightful, Relatable, Helpful, Solutif)
 * with optimistic updates. Shows breakdown when total >= 50 and
 * "Solutif Recommendation" badge when Solutif count >= 10.
 *
 * Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6
 */
export function ReactionBar({
  contentId,
  initialSummary,
  isAuthenticated = false,
}: ReactionBarProps) {
  const { summary, isLoading, error, react, removeReaction } =
    useOptimisticReaction({ contentId, initialSummary });

  const handleReactionClick = (type: ReactionType) => {
    if (!isAuthenticated) return;

    if (summary.user_reaction === type) {
      // Clicking the same reaction removes it
      removeReaction();
    } else {
      react(type);
    }
  };

  return (
    <div className="space-y-3" role="group" aria-label="Reactions">
      {/* Solutif Recommendation badge */}
      {summary.is_solutif_recommendation && (
        <div className="inline-flex items-center gap-1.5 rounded-full bg-yellow-100 px-3 py-1 text-sm font-medium text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="currentColor"
            aria-hidden="true"
          >
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
          </svg>
          <span>Solutif Recommendation</span>
        </div>
      )}

      {/* Reaction buttons */}
      <div className="flex flex-wrap items-center gap-2">
        {REACTION_BUTTONS.map((button) => {
          const isActive = summary.user_reaction === button.type;
          const count = summary[button.type];

          return (
            <button
              key={button.type}
              type="button"
              onClick={() => handleReactionClick(button.type)}
              disabled={!isAuthenticated || isLoading}
              aria-pressed={isActive}
              aria-label={`${button.label}${count > 0 ? ` (${count})` : ''}`}
              className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium transition-colors min-h-[44px] min-w-[44px]',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                'disabled:opacity-50 disabled:cursor-not-allowed',
                isActive
                  ? 'bg-primary text-primary-foreground shadow-sm'
                  : 'bg-secondary text-secondary-foreground hover:bg-secondary/80'
              )}
            >
              {button.icon}
              <span>{button.label}</span>
              {count > 0 && (
                <span
                  className={cn(
                    'ml-0.5 text-xs rounded-full px-1.5 py-0.5',
                    isActive
                      ? 'bg-primary-foreground/20 text-primary-foreground'
                      : 'bg-muted text-muted-foreground'
                  )}
                >
                  {count}
                </span>
              )}
            </button>
          );
        })}
      </div>

      {/* Total count */}
      <div className="flex items-center gap-2 text-sm text-muted-foreground">
        <span>
          {summary.total} {summary.total === 1 ? 'reaction' : 'reactions'}
        </span>
      </div>

      {/* Breakdown (visible at 50+ reactions) */}
      {summary.show_breakdown && (
        <div
          className="flex flex-wrap gap-2"
          aria-label="Reaction breakdown"
        >
          {REACTION_BUTTONS.map((button) => {
            const count = summary[button.type];
            if (count === 0) return null;
            const percentage =
              summary.total > 0
                ? Math.round((count / summary.total) * 100)
                : 0;

            return (
              <span
                key={button.type}
                className="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground"
              >
                {button.label}: {count} ({percentage}%)
              </span>
            );
          })}
        </div>
      )}

      {/* Error message */}
      {error && (
        <p className="text-sm text-destructive" role="alert">
          {error}
        </p>
      )}

      {/* Auth hint for unauthenticated users */}
      {!isAuthenticated && (
        <p className="text-xs text-muted-foreground">
          Sign in to react to this content.
        </p>
      )}
    </div>
  );
}

export default ReactionBar;
