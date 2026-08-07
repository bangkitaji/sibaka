import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { ReactionBar } from '../../../resources/js/Components/Content/ReactionBar';
import type { ReactionSummary } from '../../../resources/js/types/index.d';

/**
 * Unit tests for ReactionBar component.
 * Tests rendering of reaction buttons, badge display, breakdown visibility,
 * and authentication gating.
 *
 * Validates: Requirements 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.8
 */

const mockFetch = vi.fn();
global.fetch = mockFetch;

function setupCsrfMeta() {
  const meta = document.createElement('meta');
  meta.setAttribute('name', 'csrf-token');
  meta.setAttribute('content', 'test-csrf-token');
  document.head.appendChild(meta);
}

function removeCsrfMeta() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  if (meta) meta.remove();
}

function createSummary(overrides: Partial<ReactionSummary> = {}): ReactionSummary {
  return {
    total: 0,
    insightful: 0,
    relatable: 0,
    helpful: 0,
    solutif: 0,
    user_reaction: null,
    show_breakdown: false,
    is_solutif_recommendation: false,
    ...overrides,
  };
}

describe('ReactionBar', () => {
  beforeEach(() => {
    setupCsrfMeta();
    mockFetch.mockReset();
    mockFetch.mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ data: createSummary() }),
    });
  });

  afterEach(() => {
    removeCsrfMeta();
  });

  it('renders all four reaction buttons', () => {
    render(
      <ReactionBar
        contentId="test-id"
        initialSummary={createSummary()}
        isAuthenticated={true}
      />
    );

    expect(screen.getByRole('button', { name: /insightful/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /relatable/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /helpful/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /solutif/i })).toBeInTheDocument();
  });

  it('displays total reaction count', () => {
    render(
      <ReactionBar
        contentId="test-id"
        initialSummary={createSummary({ total: 42 })}
        isAuthenticated={true}
      />
    );

    expect(screen.getByText('42 reactions')).toBeInTheDocument();
  });

  it('uses singular "reaction" for count of 1', () => {
    render(
      <ReactionBar
        contentId="test-id"
        initialSummary={createSummary({ total: 1, insightful: 1 })}
        isAuthenticated={true}
      />
    );

    expect(screen.getByText('1 reaction')).toBeInTheDocument();
  });

  it('highlights the user active reaction', () => {
    render(
      <ReactionBar
        contentId="test-id"
        initialSummary={createSummary({
          total: 1,
          helpful: 1,
          user_reaction: 'helpful',
        })}
        isAuthenticated={true}
      />
    );

    const helpfulButton = screen.getByRole('button', { name: /helpful/i });
    expect(helpfulButton).toHaveAttribute('aria-pressed', 'true');
  });

  it('does not show breakdown when total < 50', () => {
    render(
      <ReactionBar
        contentId="test-id"
        initialSummary={createSummary({
          total: 49,
          insightful: 20,
          helpful: 29,
          show_breakdown: false,
        })}
        isAuthenticated={true}
      />
    );

    expect(screen.queryByLabelText('Reaction breakdown')).not.toBeInTheDocument();
  });

  it('shows breakdown when total >= 50', () => {
    render(
      <ReactionBar
        contentId="test-id"
        initialSummary={createSummary({
          total: 60,
          insightful: 30,
          helpful: 20,
          solutif: 10,
          show_breakdown: true,
        })}
        isAuthenticated={true}
      />
    );

    const breakdown = screen.getByLabelText('Reaction breakdown');
    expect(breakdown).toBeInTheDocument();
    expect(screen.getByText(/Insightful: 30/)).toBeInTheDocument();
    expect(screen.getByText(/Helpful: 20/)).toBeInTheDocument();
    expect(screen.getByText(/Solutif: 10/)).toBeInTheDocument();
  });

  it('shows Solutif Recommendation badge when solutif >= 10', () => {
    render(
      <ReactionBar
        contentId="test-id"
        initialSummary={createSummary({
          total: 15,
          solutif: 10,
          is_solutif_recommendation: true,
        })}
        isAuthenticated={true}
      />
    );

    expect(screen.getByText('Solutif Recommendation')).toBeInTheDocument();
  });

  it('does not show Solutif Recommendation badge when solutif < 10', () => {
    render(
      <ReactionBar
        contentId="test-id"
        initialSummary={createSummary({
          total: 9,
          solutif: 9,
          is_solutif_recommendation: false,
        })}
        isAuthenticated={true}
      />
    );

    expect(screen.queryByText('Solutif Recommendation')).not.toBeInTheDocument();
  });

  it('disables buttons when user is not authenticated', () => {
    render(
      <ReactionBar
        contentId="test-id"
        initialSummary={createSummary()}
        isAuthenticated={false}
      />
    );

    const buttons = screen.getAllByRole('button');
    buttons.forEach((button) => {
      expect(button).toBeDisabled();
    });
  });

  it('shows sign-in message for unauthenticated users', () => {
    render(
      <ReactionBar
        contentId="test-id"
        initialSummary={createSummary()}
        isAuthenticated={false}
      />
    );

    expect(screen.getByText('Sign in to react to this content.')).toBeInTheDocument();
  });

  it('does not show sign-in message for authenticated users', () => {
    render(
      <ReactionBar
        contentId="test-id"
        initialSummary={createSummary()}
        isAuthenticated={true}
      />
    );

    expect(screen.queryByText('Sign in to react to this content.')).not.toBeInTheDocument();
  });

  it('calls fetch on reaction click for authenticated users', () => {
    render(
      <ReactionBar
        contentId="content-123"
        initialSummary={createSummary()}
        isAuthenticated={true}
      />
    );

    fireEvent.click(screen.getByRole('button', { name: /insightful/i }));

    expect(mockFetch).toHaveBeenCalledWith(
      '/content/content-123/reactions',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({ type: 'insightful' }),
      })
    );
  });

  it('does not call fetch on reaction click for unauthenticated users', () => {
    render(
      <ReactionBar
        contentId="content-123"
        initialSummary={createSummary()}
        isAuthenticated={false}
      />
    );

    // Button is disabled, click should not trigger fetch
    fireEvent.click(screen.getByRole('button', { name: /insightful/i }));

    expect(mockFetch).not.toHaveBeenCalled();
  });

  it('shows count badges on reaction buttons', () => {
    render(
      <ReactionBar
        contentId="test-id"
        initialSummary={createSummary({
          total: 10,
          insightful: 5,
          helpful: 3,
          relatable: 2,
        })}
        isAuthenticated={true}
      />
    );

    // The button labels include the count for aria
    expect(screen.getByRole('button', { name: /insightful \(5\)/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /helpful \(3\)/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /relatable \(2\)/i })).toBeInTheDocument();
  });

  it('has proper accessibility attributes', () => {
    render(
      <ReactionBar
        contentId="test-id"
        initialSummary={createSummary({ user_reaction: null })}
        isAuthenticated={true}
      />
    );

    const group = screen.getByRole('group', { name: /reactions/i });
    expect(group).toBeInTheDocument();

    const button = screen.getByRole('button', { name: /insightful/i });
    expect(button).toHaveAttribute('aria-pressed', 'false');
  });
});
