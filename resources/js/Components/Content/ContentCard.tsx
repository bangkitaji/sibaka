import { Link } from '@inertiajs/react';
import { Card, CardContent } from '@/Components/UI/card';
import { CategoryBadge } from './CategoryBadge';
import type { Content, Tag } from '@/types/index.d';

interface ContentCardProps {
  content: Content;
}

function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

function getExcerpt(html: string, maxLength = 150): string {
  // Strip HTML tags and get plain text excerpt
  const text = html.replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim();
  if (text.length <= maxLength) return text;
  return text.slice(0, maxLength).trimEnd() + '...';
}

export function ContentCard({ content }: ContentCardProps) {
  const authorName = content.is_anonymous
    ? 'Anonymous Member'
    : content.author?.name ?? 'Unknown';

  return (
    <Card className="transition-shadow hover:shadow-md">
      <CardContent className="p-4">
        <Link
          href={`/content/${content.id}`}
          className="block focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 rounded-md"
        >
          <div className="space-y-3">
            {/* Title row with category badge */}
            <div className="flex flex-wrap items-start gap-2">
              <CategoryBadge category={content.category} />
              <h3 className="flex-1 text-lg font-semibold text-foreground leading-tight line-clamp-2">
                {content.title}
              </h3>
            </div>

            {/* Excerpt */}
            <p className="text-sm text-muted-foreground line-clamp-3">
              {getExcerpt(content.body_html)}
            </p>

            {/* Tags */}
            {content.tags && content.tags.length > 0 && (
              <div className="flex flex-wrap gap-1.5">
                {content.tags.map((tag: Tag) => (
                  <span
                    key={tag.id}
                    className="inline-flex items-center rounded-md bg-secondary px-2 py-0.5 text-xs text-secondary-foreground"
                  >
                    #{tag.name}
                  </span>
                ))}
              </div>
            )}

            {/* Meta row */}
            <div className="flex items-center justify-between text-xs text-muted-foreground pt-1 border-t border-border">
              <span>{authorName}</span>
              <div className="flex items-center gap-3">
                {content.reactions_summary && (
                  <span aria-label={`${content.reactions_summary.total} reactions`}>
                    {content.reactions_summary.total} reactions
                  </span>
                )}
                {content.published_at && (
                  <time dateTime={content.published_at}>
                    {formatDate(content.published_at)}
                  </time>
                )}
              </div>
            </div>
          </div>
        </Link>
      </CardContent>
    </Card>
  );
}

export default ContentCard;
