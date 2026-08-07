import { useCallback, useEffect, useRef, useState } from 'react';
import { Input } from '@/Components/UI/input';
import { cn } from '@/lib/utils';
import type { Tag, TagCategory } from '@/types/index.d';

export interface TagSelectorProps {
  /** Currently selected tags */
  selectedTags: Tag[];
  /** Called when a tag is added */
  onAdd: (tag: Tag) => void;
  /** Called when a tag is removed */
  onRemove: (tag: Tag) => void;
  /** Maximum number of tags allowed */
  maxCount?: number;
  /** Optional category filter for the search */
  category?: TagCategory;
  /** Placeholder text for the search input */
  placeholder?: string;
  /** Validation error message */
  error?: string;
  /** Label for the component */
  label?: string;
  /** Additional class name for the container */
  className?: string;
  /** Whether the selector is disabled */
  disabled?: boolean;
  /** ID for the input element (for label association) */
  id?: string;
}

/**
 * Reusable tag selection component with autocomplete search.
 *
 * Features:
 * - Search triggers at 2+ characters (prefix matching, case-insensitive)
 * - Displays selected tags as badges with remove button
 * - Rejects non-predefined tags with inline error
 * - Debounced search (300ms)
 * - Max tag count enforcement
 */
export function TagSelector({
  selectedTags,
  onAdd,
  onRemove,
  maxCount = 3,
  category,
  placeholder = 'Search tags (type 2+ chars)...',
  error,
  label,
  className,
  disabled = false,
  id,
}: TagSelectorProps) {
  const [query, setQuery] = useState('');
  const [suggestions, setSuggestions] = useState<Tag[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isOpen, setIsOpen] = useState(false);
  const [inlineError, setInlineError] = useState<string | null>(null);
  const [highlightedIndex, setHighlightedIndex] = useState(-1);

  const containerRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const abortControllerRef = useRef<AbortController | null>(null);

  const inputId = id ?? 'tag-selector-input';

  // Debounced search
  const searchTags = useCallback(
    async (searchQuery: string) => {
      const trimmed = searchQuery.trim();

      if (trimmed.length < 2) {
        setSuggestions([]);
        setIsOpen(false);
        return;
      }

      // Cancel any in-flight request
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }

      const controller = new AbortController();
      abortControllerRef.current = controller;

      setIsLoading(true);

      try {
        const params = new URLSearchParams({ q: trimmed });
        if (category) {
          params.set('category', category);
        }

        const response = await fetch(`/api/tags/search?${params.toString()}`, {
          signal: controller.signal,
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
        });

        if (!response.ok) {
          setSuggestions([]);
          setIsOpen(false);
          return;
        }

        const data: Array<{ id: string; name: string; tag_category: string }> =
          await response.json();

        // Filter out already selected tags
        const selectedIds = new Set(selectedTags.map((t) => t.id));
        const filtered = data
          .filter((t) => !selectedIds.has(t.id))
          .map((t) => ({
            id: t.id,
            name: t.name,
            tag_category: t.tag_category as TagCategory,
          }));

        setSuggestions(filtered);
        setIsOpen(filtered.length > 0);
        setHighlightedIndex(-1);
      } catch (err) {
        if (err instanceof DOMException && err.name === 'AbortError') {
          // Request was aborted, ignore
          return;
        }
        setSuggestions([]);
        setIsOpen(false);
      } finally {
        setIsLoading(false);
      }
    },
    [category, selectedTags]
  );

  // Handle query changes with debounce
  useEffect(() => {
    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }

    // Clear inline error when user starts typing again
    if (inlineError) {
      setInlineError(null);
    }

    debounceRef.current = setTimeout(() => {
      searchTags(query);
    }, 300);

    return () => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [query, searchTags]);

  // Close dropdown on outside click
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (
        containerRef.current &&
        !containerRef.current.contains(event.target as Node)
      ) {
        setIsOpen(false);
      }
    }

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Cleanup abort controller on unmount
  useEffect(() => {
    return () => {
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }
    };
  }, []);

  const handleSelect = (tag: Tag) => {
    if (selectedTags.length >= maxCount) {
      return;
    }

    onAdd(tag);
    setQuery('');
    setSuggestions([]);
    setIsOpen(false);
    setInlineError(null);
    inputRef.current?.focus();
  };

  const handleRemove = (tag: Tag) => {
    onRemove(tag);
  };

  const handleInputKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setHighlightedIndex((prev) =>
        prev < suggestions.length - 1 ? prev + 1 : 0
      );
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setHighlightedIndex((prev) =>
        prev > 0 ? prev - 1 : suggestions.length - 1
      );
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (highlightedIndex >= 0 && highlightedIndex < suggestions.length) {
        handleSelect(suggestions[highlightedIndex]);
      } else if (query.trim().length >= 2 && suggestions.length === 0 && !isLoading) {
        // User typed something that doesn't match any predefined tag
        setInlineError('Only predefined tags are accepted. Please select from the suggestions.');
        setQuery('');
      }
    } else if (e.key === 'Escape') {
      setIsOpen(false);
      setHighlightedIndex(-1);
    }
  };

  const atMaxCount = selectedTags.length >= maxCount;

  return (
    <div ref={containerRef} className={cn('space-y-2', className)}>
      {label && (
        <label htmlFor={inputId} className="text-sm font-medium text-foreground">
          {label}
        </label>
      )}

      {/* Selected tags as badges */}
      {selectedTags.length > 0 && (
        <div className="flex flex-wrap gap-1.5" role="list" aria-label="Selected tags">
          {selectedTags.map((tag) => (
            <span
              key={tag.id}
              role="listitem"
              className="inline-flex items-center gap-1 rounded-md bg-primary/10 px-2 py-1 text-xs font-medium text-primary"
            >
              #{tag.name}
              <button
                type="button"
                onClick={() => handleRemove(tag)}
                disabled={disabled}
                className="ml-0.5 rounded-sm text-primary/60 hover:text-primary focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring min-h-[20px] min-w-[20px] inline-flex items-center justify-center"
                aria-label={`Remove tag ${tag.name}`}
              >
                &times;
              </button>
            </span>
          ))}
        </div>
      )}

      {/* Search input with autocomplete */}
      <div className="relative">
        <Input
          ref={inputRef}
          id={inputId}
          type="text"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          onKeyDown={handleInputKeyDown}
          onFocus={() => {
            if (suggestions.length > 0) {
              setIsOpen(true);
            }
          }}
          placeholder={atMaxCount ? `Maximum ${maxCount} tags selected` : placeholder}
          disabled={disabled || atMaxCount}
          role="combobox"
          aria-expanded={isOpen}
          aria-controls="tag-suggestions-listbox"
          aria-activedescendant={
            highlightedIndex >= 0
              ? `tag-suggestion-${suggestions[highlightedIndex]?.id}`
              : undefined
          }
          aria-invalid={!!(error || inlineError)}
          aria-describedby={
            [
              error ? `${inputId}-error` : null,
              inlineError ? `${inputId}-inline-error` : null,
            ]
              .filter(Boolean)
              .join(' ') || undefined
          }
          autoComplete="off"
        />

        {/* Loading indicator */}
        {isLoading && (
          <div className="absolute right-3 top-1/2 -translate-y-1/2">
            <div
              className="h-4 w-4 animate-spin rounded-full border-2 border-muted-foreground border-t-transparent"
              aria-hidden="true"
            />
          </div>
        )}

        {/* Autocomplete dropdown */}
        {isOpen && suggestions.length > 0 && (
          <ul
            id="tag-suggestions-listbox"
            role="listbox"
            aria-label="Tag suggestions"
            className="absolute z-50 mt-1 w-full max-h-60 overflow-auto rounded-md border border-input bg-background py-1 shadow-md"
          >
            {suggestions.map((tag, index) => (
              <li
                key={tag.id}
                id={`tag-suggestion-${tag.id}`}
                role="option"
                aria-selected={highlightedIndex === index}
                className={cn(
                  'cursor-pointer px-3 py-2 text-sm transition-colors min-h-touch flex items-center',
                  highlightedIndex === index
                    ? 'bg-accent text-accent-foreground'
                    : 'text-foreground hover:bg-accent/50'
                )}
                onClick={() => handleSelect(tag)}
                onMouseEnter={() => setHighlightedIndex(index)}
              >
                <span className="font-medium">#{tag.name}</span>
                <span className="ml-auto text-xs text-muted-foreground">
                  {formatCategoryLabel(tag.tag_category)}
                </span>
              </li>
            ))}
          </ul>
        )}
      </div>

      {/* Inline error for non-predefined tags */}
      {inlineError && (
        <p
          id={`${inputId}-inline-error`}
          className="text-sm text-destructive"
          role="alert"
        >
          {inlineError}
        </p>
      )}

      {/* Validation error from parent */}
      {error && (
        <p id={`${inputId}-error`} className="text-sm text-destructive" role="alert">
          {error}
        </p>
      )}
    </div>
  );
}

function formatCategoryLabel(category: TagCategory): string {
  switch (category) {
    case 'tech_stack':
      return 'Tech Stack';
    case 'experience_level':
      return 'Experience Level';
    case 'content_category':
      return 'Category';
    default:
      return category;
  }
}

export default TagSelector;
