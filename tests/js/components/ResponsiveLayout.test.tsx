import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import React from 'react';

// Mock @inertiajs/react
vi.mock('@inertiajs/react', () => ({
  Head: ({ title }: { title?: string }) => <title>{title}</title>,
  Link: ({
    href,
    children,
    className,
    method,
    as,
    onClick,
    ...rest
  }: {
    href: string;
    children: React.ReactNode;
    className?: string;
    method?: string;
    as?: string;
    onClick?: () => void;
  }) => {
    const Component = as === 'button' ? 'button' : 'a';
    return (
      <Component href={href} className={className} onClick={onClick} {...rest}>
        {children}
      </Component>
    );
  },
  usePage: () => ({
    props: {
      auth: { user: { name: 'Test User', email: 'test@example.com', role: 'member' } },
    },
  }),
}));

import AppLayout from '@/Layouts/AppLayout';

describe('AppLayout - Responsive Layout', () => {
  describe('Mobile Navigation Menu', () => {
    it('renders a hamburger menu button visible on mobile', () => {
      render(
        <AppLayout title="Test">
          <p>Content</p>
        </AppLayout>
      );

      const hamburger = screen.getByLabelText('Open navigation menu');
      expect(hamburger).toBeInTheDocument();
      // The hamburger should have tablet:hidden class (hidden on tablet+)
      expect(hamburger.className).toContain('tablet:hidden');
    });

    it('shows mobile menu when hamburger is clicked', () => {
      render(
        <AppLayout title="Test">
          <p>Content</p>
        </AppLayout>
      );

      const hamburger = screen.getByLabelText('Open navigation menu');
      fireEvent.click(hamburger);

      // Mobile menu should now be visible
      const mobileMenu = screen.getByRole('navigation').querySelector('#mobile-menu');
      expect(mobileMenu).toBeInTheDocument();
    });

    it('closes mobile menu when close button is clicked', () => {
      render(
        <AppLayout title="Test">
          <p>Content</p>
        </AppLayout>
      );

      // Open menu
      const hamburger = screen.getByLabelText('Open navigation menu');
      fireEvent.click(hamburger);

      // Close menu
      const closeButton = screen.getByLabelText('Close navigation menu');
      fireEvent.click(closeButton);

      // Mobile menu should be hidden
      const mobileMenu = screen.getByRole('navigation').querySelector('#mobile-menu');
      expect(mobileMenu).toBeNull();
    });

    it('renders mobile nav links with min-h-touch for 44px touch targets', () => {
      render(
        <AppLayout title="Test">
          <p>Content</p>
        </AppLayout>
      );

      // Open mobile menu
      const hamburger = screen.getByLabelText('Open navigation menu');
      fireEvent.click(hamburger);

      // Check that mobile menu links have min-h-touch class
      const mobileMenu = screen.getByRole('navigation').querySelector('#mobile-menu');
      const links = mobileMenu?.querySelectorAll('a, button');
      links?.forEach((link) => {
        expect(link.className).toContain('min-h-touch');
      });
    });

    it('hamburger button itself has 44px minimum touch target', () => {
      render(
        <AppLayout title="Test">
          <p>Content</p>
        </AppLayout>
      );

      const hamburger = screen.getByLabelText('Open navigation menu');
      expect(hamburger.className).toContain('min-h-touch');
      expect(hamburger.className).toContain('min-w-touch');
    });
  });

  describe('Desktop Navigation', () => {
    it('desktop nav links are hidden on mobile (hidden class present)', () => {
      render(
        <AppLayout title="Test">
          <p>Content</p>
        </AppLayout>
      );

      // The desktop nav container uses hidden tablet:flex
      const nav = screen.getByRole('navigation');
      const desktopLinks = nav.querySelector('.hidden.tablet\\:flex');
      expect(desktopLinks).toBeInTheDocument();
    });

    it('desktop nav links have min-h-touch and min-w-touch', () => {
      render(
        <AppLayout title="Test">
          <p>Content</p>
        </AppLayout>
      );

      const nav = screen.getByRole('navigation');
      const desktopLinksContainer = nav.querySelector('.hidden.tablet\\:flex.tablet\\:items-center');
      if (desktopLinksContainer) {
        const links = desktopLinksContainer.querySelectorAll('a');
        links.forEach((link) => {
          expect(link.className).toContain('min-h-touch');
          expect(link.className).toContain('min-w-touch');
        });
      }
    });
  });

  describe('Layout Structure', () => {
    it('applies overflow-x-hidden to the root container', () => {
      render(
        <AppLayout title="Test">
          <p>Content</p>
        </AppLayout>
      );

      // The root div in AppLayout has overflow-x-hidden
      const rootDiv = screen.getByRole('navigation').closest('.min-h-screen');
      expect(rootDiv?.className).toContain('overflow-x-hidden');
    });

    it('main content area uses responsive padding', () => {
      render(
        <AppLayout title="Test">
          <p data-testid="content">Content</p>
        </AppLayout>
      );

      const main = screen.getByRole('main');
      // Should have px-4 (mobile), tablet:px-6, desktop:px-8
      expect(main.className).toContain('px-4');
      expect(main.className).toContain('tablet:px-6');
      expect(main.className).toContain('desktop:px-8');
    });

    it('renders children inside the main content area', () => {
      render(
        <AppLayout title="Test">
          <p data-testid="child-content">Hello</p>
        </AppLayout>
      );

      const main = screen.getByRole('main');
      expect(main).toContainElement(screen.getByTestId('child-content'));
    });
  });
});
