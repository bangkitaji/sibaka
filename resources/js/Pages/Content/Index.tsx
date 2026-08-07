import { useCallback, useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/UI/button';
import { Card, CardContent } from '@/Components/UI/card';
import ContentCard from '@/Components/Content/ContentCard';
import type { Content, ContentCategory, PaginatedResponse, Tag } from '@/types/index.d';

interface ContentFilters {
  categories: ContentCategory[];
  tags: string[];
  page: number;
}

interface ContentIndexProps {
  contents: PaginatedResponse<Content>;
  filters: {
    categories: ContentCategory[];
    tags: string[];
  };
  availableTags: Tag[];
}

const CATEGORY_OPTIONS: { value: ContentCategory; label: string }[] = [
  { value: 'post_mortem', label: 'Post-Mortem' },
  { value: 'tech_stack', label: 'Tech Stack & Architecture' },
  { value: 'career_interview', label: 'Career & Interview' },
  { value: 'showcase', label: 'Showcase' },
];

export default function ContentIndex({
  contents,
  filters,
  availableTags,
}: ContentIndexProps) {
  const [selectedCategories, setSelectedCategories] = useState<ContentCategory[]>(
    filters.categories ?? []
  );
  const [selectedTags, setSelectedTags] = useState<string[]>(filters.tags ?? []);

  const applyFilters = useCallback(
    (overrides: Partial<ContentFilters> = {}) => {
      const cats = overrides.categories ?? selectedCategories;
      const tags = overrides.tags ?? selectedTags;

      const params: Record<string, string | string[]> = {};
      if (cats.length > 0) params['categories[]'] = cats;
      if (tags.length > 0) params['tags[]'] = tags;

      router.get('/content', params as Record<string, string>, {
        preserveState: true,
        preserveScroll: true,
      });
    },
    [selectedCategories, selectedTags]
  );

  const toggleCategory = (category: ContentCategory) => {
    const updated = selectedCategories.includes(category)
      ? selectedCategories.filter((c) => c !== category)
      : [...selectedCategories, category];
    setSelectedCategories(updated);
    applyFilters({ categories: updated });
  };

  const toggleTag = (tagName: string) => {
    const updated = selectedTags.includes(tagName)
      ? selectedTags.filter((t) => t !== tagName)
      : [...selectedTags, tagName];
    setSelectedTags(updated);
    applyFilters({ tags: updated });
  };

  const clearFilters = () => {
    setSelectedCategories([]);
    setSelectedTags([]);
    router.get('/content', {}, { preserveState: true, preserveScroll: true });
  };

  const goToPage = (page: number) => {
    const params: Record<string, string | string[]> = { page: String(page) };
    if (selectedCategories.length > 0) params['categories[]'] = selectedCategories;
    if (selectedTags.length > 0) params['tags[]'] = selectedTags;
    router.get('/content', params as Record<string, string>, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const hasActiveFilters = selectedCategories.length > 0 || selectedTags.length > 0;

  return (
    <AppLayout title="Content">
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col gap-4 tablet:flex-row tablet:items-center tablet:justify-between">
          <div>
            <h1 className="text-2xl font-bold text-foreground">Content</h1>
            <p className="mt-1 text-sm text-muted-foreground">
              Explore shared knowledge from the SIBAKA community.
            </p>
          </div>
          <Button
            onClick={() => router.visit('/content/create')}
            aria-label="Create new content"
          >
            Create Content
          </Button>
        </div>

        <div className="grid grid-cols-1 gap-6 desktop:grid-cols-[1fr_280px]">
          {/* Main content area */}
          <div className="space-y-4">
            {/* Category filter (multi-select) */}
            <div className="flex flex-wrap items-center gap-2" role="group" aria-label="Filter by category">
              {CATEGORY_OPTIONS.map((option) => {
                const isSelected = selectedCategories.includes(option.value);
                return (
                  <Button
                    key={option.value}
                    variant={isSelected ? 'default' : 'outline'}
                    size="sm"
                    onClick={() => toggleCategory(option.value)}
                    aria-pressed={isSelected}
                    aria-label={`Filter by ${option.label}`}
                  >
                    {option.label}
                  </Button>
                );
              })}
              {hasActiveFilters && (
                <Button variant="ghost" size="sm" onClick={clearFilters}>
                  Clear
                </Button>
              )}
            </div>

            {/* Results count */}
            <p className="text-sm text-muted-foreground">
              {contents.total} {contents.total === 1 ? 'article' : 'articles'} found
            </p>

            {/* Content list */}
            {contents.data.length > 0 ? (
              <div className="space-y-4">
                {contents.data.map((content) => (
                  <ContentCard key={content.id} content={content} />
                ))}
              </div>
            ) : (
              <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-border py-12 px-4 text-center">
                <h3 className="text-lg font-medium text-foreground">No content found</h3>
                <p className="mt-1 text-sm text-muted-foreground max-w-sm">
                  No content matches your current filters. Try adjusting your selection
                  or creating new content.
                </p>
                {hasActiveFilters && (
                  <Button variant="outline" className="mt-4" onClick={clearFilters}>
                    Clear all filters
                  </Button>
                )}
              </div>
            )}

            {/* Pagination */}
            {contents.last_page > 1 && (
              <nav aria-label="Content pagination" className="flex items-center justify-center gap-2 pt-4">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => goToPage(contents.current_page - 1)}
                  disabled={contents.current_page <= 1}
                  aria-label="Previous page"
                >
                  Previous
                </Button>
                <span className="text-sm text-muted-foreground px-3">
                  Page {contents.current_page} of {contents.last_page}
                </span>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => goToPage(contents.current_page + 1)}
                  disabled={contents.current_page >= contents.last_page}
                  aria-label="Next page"
                >
                  Next
                </Button>
              </nav>
            )}
          </div>

          {/* Sidebar: Tag facets */}
          <aside className="space-y-4" aria-label="Tag filters">
            <Card>
              <CardContent className="p-4">
                <h2 className="text-sm font-semibold text-foreground mb-3">
                  Filter by Tags
                </h2>
                {availableTags && availableTags.length > 0 ? (
                  <div className="flex flex-wrap gap-2">
                    {availableTags.map((tag) => {
                      const isSelected = selectedTags.includes(tag.name);
                      return (
                        <button
                          key={tag.id}
                          type="button"
                          onClick={() => toggleTag(tag.name)}
                          className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium transition-colors min-h-[32px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${
                            isSelected
                              ? 'bg-primary text-primary-foreground'
                              : 'bg-secondary text-secondary-foreground hover:bg-secondary/80'
                          }`}
                          aria-pressed={isSelected}
                          aria-label={`Filter by tag ${tag.name}`}
                        >
                          #{tag.name}
                        </button>
                      );
                    })}
                  </div>
                ) : (
                  <p className="text-xs text-muted-foreground">No tags available.</p>
                )}
              </CardContent>
            </Card>
          </aside>
        </div>
      </div>
    </AppLayout>
  );
}
