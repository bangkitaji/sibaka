import { Link, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { CategoryBadge } from '@/Components/Content/CategoryBadge';
import { ReactionBar } from '@/Components/Content/ReactionBar';
import type { Content, SharedPageProps, Tag } from '@/types/index.d';

interface ContentShowProps {
  content: Content;
}

function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

export default function ContentShow({ content }: ContentShowProps) {
  const { auth } = usePage<SharedPageProps>().props;

  const isAuthor = auth.user && content.author_id === auth.user.id;

  return (
    <AppLayout title={content.title}>
      <article className="max-w-4xl mx-auto space-y-6">
        {/* Header */}
        <header className="space-y-4">
          {/* Category badge + Title */}
          <div className="flex flex-wrap items-start gap-3">
            <CategoryBadge category={content.category} />
            {content.is_qna && (
              <span className="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300">
                Q&A
              </span>
            )}
          </div>
          <h1 className="text-3xl font-bold text-foreground leading-tight">
            {content.title}
          </h1>

          {/* Meta info */}
          <div className="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
            <span>
              By{' '}
              {content.is_anonymous ? (
                <span className="font-medium">Anonymous Member</span>
              ) : content.author ? (
                <span className="font-medium">{content.author.name}</span>
              ) : (
                <span className="font-medium">Unknown</span>
              )}
            </span>
            {content.published_at && (
              <time dateTime={content.published_at}>
                {formatDate(content.published_at)}
              </time>
            )}
            {content.is_locked && (
              <span className="inline-flex items-center text-amber-600 dark:text-amber-400">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="14"
                  height="14"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  className="mr-1"
                  aria-hidden="true"
                >
                  <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
                  <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
                Locked
              </span>
            )}
          </div>

          {/* Tags */}
          {content.tags && content.tags.length > 0 && (
            <div className="flex flex-wrap gap-2" aria-label="Content tags">
              {content.tags.map((tag: Tag) => (
                <span
                  key={tag.id}
                  className="inline-flex items-center rounded-md bg-secondary px-2.5 py-1 text-xs font-medium text-secondary-foreground"
                >
                  #{tag.name}
                </span>
              ))}
            </div>
          )}

          {/* Edit button for author */}
          {isAuthor && (
            <div>
              <Link
                href={`/content/${content.id}/edit`}
                className="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-11 rounded-md px-3 min-h-touch min-w-touch"
              >
                Edit Content
              </Link>
            </div>
          )}
        </header>

        {/* Content body - rendered HTML from TipTap */}
        <div
          className="prose prose-lg dark:prose-invert max-w-none [&_pre]:bg-gray-900 [&_pre]:text-gray-100 [&_pre]:rounded-md [&_pre]:p-4 [&_pre]:overflow-x-auto [&_pre_code]:bg-transparent [&_pre_code]:text-inherit [&_pre_code]:p-0 [&_.hljs-keyword]:text-purple-400 [&_.hljs-string]:text-green-400 [&_.hljs-number]:text-orange-400 [&_.hljs-comment]:text-gray-500 [&_.hljs-function]:text-blue-400 [&_.hljs-built_in]:text-cyan-400 [&_.hljs-title]:text-yellow-400"
          dangerouslySetInnerHTML={{ __html: content.body_html }}
        />

        {/* Reactions section */}
        {content.reactions_summary && (
          <section
            className="border-t border-border pt-6"
            aria-label="Reactions"
          >
            <ReactionBar
              contentId={content.id}
              initialSummary={content.reactions_summary}
              isAuthenticated={!!auth.user}
            />
          </section>
        )}

        {/* Back link */}
        <div className="border-t border-border pt-6">
          <Link
            href="/content"
            className="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 hover:bg-accent hover:text-accent-foreground h-11 rounded-md px-3 min-h-touch min-w-touch"
          >
            &larr; Back to Content
          </Link>
        </div>
      </article>
    </AppLayout>
  );
}
