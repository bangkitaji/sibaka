import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, act } from '@testing-library/react';
import { TagSelector } from '@/Components/Tags/TagSelector';
import type { Tag } from '@/types/index.d';

// Mock fetch globally
const mockFetch = vi.fn();
global.fetch = mockFetch;

function createTag(name: string, category: 'tech_stack' | 'experience_level' | 'content_category' = 'tech_stack'): Tag {
  return {
    id: `tag-${name}`,
    name,
    tag_category: category,
  };
}

function mockSearchResponse(tags: Array<{ id: string; name: string; tag_category: string }>) {
  mockFetch.mockResolvedValueOnce({
    ok: true,
    json: () => Promise.resolve(tags),
  });
}

/** Advance fake timers and flush microtasks for async operations */
async function advanceTimersAndFlush(ms: number) {
  await act(async () => {
    vi.advanceTimersByTime(ms);
    // Flush microtask queue (resolves pending promises)
    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();
  });
}

describe('TagSelector', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    mockFetch.mockReset();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  describe('rendering', () => {
    it('renders the search input with placeholder', () => {
      render(
        <TagSelector
          selectedTags={[]}
          onAdd={vi.fn()}
          onRemove={vi.fn()}
        />
      );

      expect(screen.getByRole('combobox')).toBeInTheDocument();
      expect(screen.getByPlaceholderText('Search tags (type 2+ chars)...')).toBeInTheDocument();
    });

    it('renders with custom placeholder', () => {
      render(
        <TagSelector
          selectedTags={[]}
          onAdd={vi.fn()}
          onRemove={vi.fn()}
          placeholder="Find a tag..."
        />
      );

      expect(screen.getByPlaceholderText('Find a tag...')).toBeInTheDocument();
    });

    it('renders with a label', () => {
      render(
        <TagSelector
          selectedTags={[]}
          onAdd={vi.fn()}
          onRemove={vi.fn()}
          label="Tech Stack Tags"
          id="tech-tags"
        />
      );

      expect(screen.getByText('Tech Stack Tags')).toBeInTheDocument();
    });

    it('renders selected tags as badges', () => {
      const tags = [createTag('react'), createTag('typescript')];

      render(
        <TagSelector
          selectedTags={tags}
          onAdd={vi.fn()}
          onRemove={vi.fn()}
        />
      );

      expect(screen.getByText('#react')).toBeInTheDocument();
      expect(screen.getByText('#typescript')).toBeInTheDocument();
    });

    it('renders remove buttons for selected tags', () => {
      const tags = [createTag('react')];

      render(
        <TagSelector
          selectedTags={tags}
          onAdd={vi.fn()}
          onRemove={vi.fn()}
        />
      );

      expect(screen.getByRole('button', { name: 'Remove tag react' })).toBeInTheDocument();
    });
  });

  describe('search behavior', () => {
    it('does not trigger search for less than 2 characters', async () => {
      render(
        <TagSelector
          selectedTags={[]}
          onAdd={vi.fn()}
          onRemove={vi.fn()}
        />
      );

      const input = screen.getByRole('combobox');
      fireEvent.change(input, { target: { value: 'r' } });

      await advanceTimersAndFlush(400);

      expect(mockFetch).not.toHaveBeenCalled();
    });

    it('triggers search at 2+ characters after debounce', async () => {
      mockSearchResponse([
        { id: 'tag-react', name: 'react', tag_category: 'tech_stack' },
      ]);

      render(
        <TagSelector
          selectedTags={[]}
          onAdd={vi.fn()}
          onRemove={vi.fn()}
        />
      );

      const input = screen.getByRole('combobox');
      fireEvent.change(input, { target: { value: 're' } });

      await advanceTimersAndFlush(400);

      expect(mockFetch).toHaveBeenCalledTimes(1);
      expect(mockFetch).toHaveBeenCalledWith(
        expect.stringContaining('/api/tags/search?q=re'),
        expect.any(Object)
      );
    });

    it('passes category filter to the API when provided', async () => {
      mockSearchResponse([]);

      render(
        <TagSelector
          selectedTags={[]}
          onAdd={vi.fn()}
          onRemove={vi.fn()}
          category="tech_stack"
        />
      );

      const input = screen.getByRole('combobox');
      fireEvent.change(input, { target: { value: 'py' } });

      await advanceTimersAndFlush(400);

      expect(mockFetch).toHaveBeenCalledWith(
        expect.stringContaining('category=tech_stack'),
        expect.any(Object)
      );
    });

    it('shows suggestions dropdown when results are returned', async () => {
      mockSearchResponse([
        { id: 'tag-react', name: 'react', tag_category: 'tech_stack' },
        { id: 'tag-redis', name: 'redis', tag_category: 'tech_stack' },
      ]);

      render(
        <TagSelector
          selectedTags={[]}
          onAdd={vi.fn()}
          onRemove={vi.fn()}
        />
      );

      const input = screen.getByRole('combobox');
      fireEvent.change(input, { target: { value: 're' } });

      await advanceTimersAndFlush(400);

      expect(screen.getByRole('listbox', { name: 'Tag suggestions' })).toBeInTheDocument();
      expect(screen.getByText('#react')).toBeInTheDocument();
      expect(screen.getByText('#redis')).toBeInTheDocument();
    });

    it('filters out already-selected tags from suggestions', async () => {
      const selectedTags = [createTag('react')];

      mockSearchResponse([
        { id: 'tag-react', name: 'react', tag_category: 'tech_stack' },
        { id: 'tag-redis', name: 'redis', tag_category: 'tech_stack' },
      ]);

      render(
        <TagSelector
          selectedTags={selectedTags}
          onAdd={vi.fn()}
          onRemove={vi.fn()}
        />
      );

      const input = screen.getByRole('combobox');
      fireEvent.change(input, { target: { value: 're' } });

      await advanceTimersAndFlush(400);

      expect(screen.getByRole('listbox', { name: 'Tag suggestions' })).toBeInTheDocument();
      // Only redis should appear, not react (already selected)
      const options = screen.getAllByRole('option');
      expect(options).toHaveLength(1);
      expect(options[0]).toHaveTextContent('#redis');
    });
  });

  describe('tag selection', () => {
    it('calls onAdd when a suggestion is clicked', async () => {
      const onAdd = vi.fn();

      mockSearchResponse([
        { id: 'tag-react', name: 'react', tag_category: 'tech_stack' },
      ]);

      render(
        <TagSelector
          selectedTags={[]}
          onAdd={onAdd}
          onRemove={vi.fn()}
        />
      );

      const input = screen.getByRole('combobox');
      fireEvent.change(input, { target: { value: 're' } });

      await advanceTimersAndFlush(400);

      expect(screen.getByRole('option')).toBeInTheDocument();

      fireEvent.click(screen.getByRole('option'));

      expect(onAdd).toHaveBeenCalledWith({
        id: 'tag-react',
        name: 'react',
        tag_category: 'tech_stack',
      });
    });

    it('clears the input after selection', async () => {
      mockSearchResponse([
        { id: 'tag-react', name: 'react', tag_category: 'tech_stack' },
      ]);

      render(
        <TagSelector
          selectedTags={[]}
          onAdd={vi.fn()}
          onRemove={vi.fn()}
        />
      );

      const input = screen.getByRole('combobox') as HTMLInputElement;
      fireEvent.change(input, { target: { value: 're' } });

      await advanceTimersAndFlush(400);

      expect(screen.getByRole('option')).toBeInTheDocument();

      fireEvent.click(screen.getByRole('option'));

      expect(input.value).toBe('');
    });

    it('calls onRemove when a tag badge remove button is clicked', () => {
      const onRemove = vi.fn();
      const tag = createTag('react');

      render(
        <TagSelector
          selectedTags={[tag]}
          onAdd={vi.fn()}
          onRemove={onRemove}
        />
      );

      fireEvent.click(screen.getByRole('button', { name: 'Remove tag react' }));

      expect(onRemove).toHaveBeenCalledWith(tag);
    });
  });

  describe('max count enforcement', () => {
    it('disables input when maxCount is reached', () => {
      const tags = [createTag('react'), createTag('typescript'), createTag('node')];

      render(
        <TagSelector
          selectedTags={tags}
          onAdd={vi.fn()}
          onRemove={vi.fn()}
          maxCount={3}
        />
      );

      const input = screen.getByRole('combobox');
      expect(input).toBeDisabled();
    });

    it('shows max count message in placeholder when at limit', () => {
      const tags = [createTag('react'), createTag('typescript'), createTag('node')];

      render(
        <TagSelector
          selectedTags={tags}
          onAdd={vi.fn()}
          onRemove={vi.fn()}
          maxCount={3}
        />
      );

      expect(screen.getByPlaceholderText('Maximum 3 tags selected')).toBeInTheDocument();
    });
  });

  describe('non-predefined tag rejection', () => {
    it('shows inline error when Enter is pressed with no matching suggestions', async () => {
      mockSearchResponse([]);

      render(
        <TagSelector
          selectedTags={[]}
          onAdd={vi.fn()}
          onRemove={vi.fn()}
        />
      );

      const input = screen.getByRole('combobox');
      fireEvent.change(input, { target: { value: 'nonexistenttag' } });

      await advanceTimersAndFlush(400);

      expect(mockFetch).toHaveBeenCalledTimes(1);

      fireEvent.keyDown(input, { key: 'Enter' });

      expect(
        screen.getByText('Only predefined tags are accepted. Please select from the suggestions.')
      ).toBeInTheDocument();
    });

    it('clears input when non-predefined tag is submitted', async () => {
      mockSearchResponse([]);

      render(
        <TagSelector
          selectedTags={[]}
          onAdd={vi.fn()}
          onRemove={vi.fn()}
        />
      );

      const input = screen.getByRole('combobox') as HTMLInputElement;
      fireEvent.change(input, { target: { value: 'nonexistenttag' } });

      await advanceTimersAndFlush(400);

      expect(mockFetch).toHaveBeenCalledTimes(1);

      fireEvent.keyDown(input, { key: 'Enter' });

      expect(input.value).toBe('');
    });

    it('clears inline error when user types again', async () => {
      mockSearchResponse([]);

      render(
        <TagSelector
          selectedTags={[]}
          onAdd={vi.fn()}
          onRemove={vi.fn()}
        />
      );

      const input = screen.getByRole('combobox');
      fireEvent.change(input, { target: { value: 'nonexistenttag' } });

      await advanceTimersAndFlush(400);

      expect(mockFetch).toHaveBeenCalledTimes(1);

      fireEvent.keyDown(input, { key: 'Enter' });

      expect(
        screen.getByText('Only predefined tags are accepted. Please select from the suggestions.')
      ).toBeInTheDocument();

      // Type again to clear error
      await act(async () => {
        fireEvent.change(input, { target: { value: 're' } });
      });

      expect(
        screen.queryByText('Only predefined tags are accepted. Please select from the suggestions.')
      ).not.toBeInTheDocument();
    });
  });

  describe('validation errors', () => {
    it('displays external validation error', () => {
      render(
        <TagSelector
          selectedTags={[]}
          onAdd={vi.fn()}
          onRemove={vi.fn()}
          error="At least 1 tech stack tag is required"
        />
      );

      expect(screen.getByText('At least 1 tech stack tag is required')).toBeInTheDocument();
    });
  });

  describe('keyboard navigation', () => {
    it('navigates suggestions with arrow keys', async () => {
      mockSearchResponse([
        { id: 'tag-react', name: 'react', tag_category: 'tech_stack' },
        { id: 'tag-redis', name: 'redis', tag_category: 'tech_stack' },
      ]);

      render(
        <TagSelector
          selectedTags={[]}
          onAdd={vi.fn()}
          onRemove={vi.fn()}
        />
      );

      const input = screen.getByRole('combobox');
      fireEvent.change(input, { target: { value: 're' } });

      await advanceTimersAndFlush(400);

      expect(screen.getAllByRole('option')).toHaveLength(2);

      fireEvent.keyDown(input, { key: 'ArrowDown' });
      expect(screen.getAllByRole('option')[0]).toHaveAttribute('aria-selected', 'true');

      fireEvent.keyDown(input, { key: 'ArrowDown' });
      expect(screen.getAllByRole('option')[1]).toHaveAttribute('aria-selected', 'true');
    });

    it('selects highlighted suggestion on Enter', async () => {
      const onAdd = vi.fn();

      mockSearchResponse([
        { id: 'tag-react', name: 'react', tag_category: 'tech_stack' },
        { id: 'tag-redis', name: 'redis', tag_category: 'tech_stack' },
      ]);

      render(
        <TagSelector
          selectedTags={[]}
          onAdd={onAdd}
          onRemove={vi.fn()}
        />
      );

      const input = screen.getByRole('combobox');
      fireEvent.change(input, { target: { value: 're' } });

      await advanceTimersAndFlush(400);

      expect(screen.getAllByRole('option')).toHaveLength(2);

      fireEvent.keyDown(input, { key: 'ArrowDown' });
      fireEvent.keyDown(input, { key: 'Enter' });

      expect(onAdd).toHaveBeenCalledWith({
        id: 'tag-react',
        name: 'react',
        tag_category: 'tech_stack',
      });
    });

    it('closes dropdown on Escape', async () => {
      mockSearchResponse([
        { id: 'tag-react', name: 'react', tag_category: 'tech_stack' },
      ]);

      render(
        <TagSelector
          selectedTags={[]}
          onAdd={vi.fn()}
          onRemove={vi.fn()}
        />
      );

      const input = screen.getByRole('combobox');
      fireEvent.change(input, { target: { value: 're' } });

      await advanceTimersAndFlush(400);

      expect(screen.getByRole('listbox', { name: 'Tag suggestions' })).toBeInTheDocument();

      fireEvent.keyDown(input, { key: 'Escape' });

      expect(screen.queryByRole('listbox', { name: 'Tag suggestions' })).not.toBeInTheDocument();
    });
  });

  describe('accessibility', () => {
    it('has proper combobox ARIA attributes', () => {
      render(
        <TagSelector
          selectedTags={[]}
          onAdd={vi.fn()}
          onRemove={vi.fn()}
        />
      );

      const input = screen.getByRole('combobox');
      expect(input).toHaveAttribute('aria-expanded', 'false');
      expect(input).toHaveAttribute('aria-controls', 'tag-suggestions-listbox');
      expect(input).toHaveAttribute('autocomplete', 'off');
    });

    it('sets aria-expanded to true when dropdown is open', async () => {
      mockSearchResponse([
        { id: 'tag-react', name: 'react', tag_category: 'tech_stack' },
      ]);

      render(
        <TagSelector
          selectedTags={[]}
          onAdd={vi.fn()}
          onRemove={vi.fn()}
        />
      );

      const input = screen.getByRole('combobox');
      fireEvent.change(input, { target: { value: 're' } });

      await advanceTimersAndFlush(400);

      expect(input).toHaveAttribute('aria-expanded', 'true');
    });

    it('marks input as aria-invalid when there is an error', () => {
      render(
        <TagSelector
          selectedTags={[]}
          onAdd={vi.fn()}
          onRemove={vi.fn()}
          error="Required field"
        />
      );

      expect(screen.getByRole('combobox')).toHaveAttribute('aria-invalid', 'true');
    });

    it('renders selected tags list with proper role', () => {
      render(
        <TagSelector
          selectedTags={[createTag('react')]}
          onAdd={vi.fn()}
          onRemove={vi.fn()}
        />
      );

      expect(screen.getByRole('list', { name: 'Selected tags' })).toBeInTheDocument();
    });
  });
});
