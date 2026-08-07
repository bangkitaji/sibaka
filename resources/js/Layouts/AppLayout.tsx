import { type PropsWithChildren, type ReactNode, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';

interface AppLayoutProps extends PropsWithChildren {
  title?: string;
  header?: ReactNode;
}

export default function AppLayout({ title, header, children }: AppLayoutProps) {
  const { auth } = usePage<{
    auth: { user: { name: string; email: string; role: string } | null };
  }>().props;

  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  return (
    <>
      {title && <Head title={title} />}

      <div className="min-h-screen bg-background text-foreground overflow-x-hidden">
        {/* Navigation */}
        <nav className="border-b border-border bg-card">
          <div className="mx-auto max-w-7xl px-4 tablet:px-6 desktop:px-8">
            <div className="flex h-16 items-center justify-between">
              {/* Logo / Brand */}
              <div className="flex items-center">
                <Link
                  href="/"
                  className="text-xl font-bold text-primary min-w-touch min-h-touch flex items-center"
                >
                  SIBAKA
                </Link>
              </div>

              {/* Desktop Navigation Links */}
              <div className="hidden tablet:flex tablet:items-center tablet:gap-4">
                <Link
                  href="/"
                  className="min-h-touch min-w-touch flex items-center justify-center rounded-md px-3 py-2 text-sm font-medium text-foreground hover:bg-accent hover:text-accent-foreground transition-colors"
                >
                  Home
                </Link>
                {auth?.user && (
                  <>
                    <Link
                      href="/directory"
                      className="min-h-touch min-w-touch flex items-center justify-center rounded-md px-3 py-2 text-sm font-medium text-foreground hover:bg-accent hover:text-accent-foreground transition-colors"
                    >
                      Directory
                    </Link>
                    <Link
                      href="/content/create"
                      className="min-h-touch min-w-touch flex items-center justify-center rounded-md px-3 py-2 text-sm font-medium text-foreground hover:bg-accent hover:text-accent-foreground transition-colors"
                    >
                      Create
                    </Link>
                  </>
                )}
              </div>

              {/* User Menu (Desktop) + Mobile Hamburger */}
              <div className="flex items-center gap-2">
                {/* Desktop auth buttons */}
                <div className="hidden tablet:flex tablet:items-center tablet:gap-3">
                  {auth?.user ? (
                    <>
                      <span className="text-sm text-muted-foreground">
                        {auth.user.name}
                      </span>
                      <Link
                        href="/profile"
                        className="min-h-touch min-w-touch flex items-center justify-center rounded-md px-3 py-2 text-sm font-medium text-foreground hover:bg-accent transition-colors"
                      >
                        Profile
                      </Link>
                      <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="min-h-touch min-w-touch flex items-center justify-center rounded-md px-3 py-2 text-sm font-medium text-destructive hover:bg-destructive/10 transition-colors"
                      >
                        Logout
                      </Link>
                    </>
                  ) : (
                    <>
                      <Link
                        href="/login"
                        className="min-h-touch min-w-touch flex items-center justify-center rounded-md px-3 py-2 text-sm font-medium text-foreground hover:bg-accent transition-colors"
                      >
                        Login
                      </Link>
                      <Link
                        href="/register"
                        className="min-h-touch min-w-touch flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
                      >
                        Register
                      </Link>
                    </>
                  )}
                </div>

                {/* Mobile hamburger button */}
                <button
                  type="button"
                  className="tablet:hidden min-h-touch min-w-touch flex items-center justify-center rounded-md p-2 text-foreground hover:bg-accent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                  aria-expanded={mobileMenuOpen}
                  aria-controls="mobile-menu"
                  aria-label={mobileMenuOpen ? 'Close navigation menu' : 'Open navigation menu'}
                >
                  {mobileMenuOpen ? (
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="24"
                      height="24"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="2"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      aria-hidden="true"
                    >
                      <line x1="18" y1="6" x2="6" y2="18" />
                      <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                  ) : (
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="24"
                      height="24"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="2"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      aria-hidden="true"
                    >
                      <line x1="4" y1="6" x2="20" y2="6" />
                      <line x1="4" y1="12" x2="20" y2="12" />
                      <line x1="4" y1="18" x2="20" y2="18" />
                    </svg>
                  )}
                </button>
              </div>
            </div>
          </div>

          {/* Mobile Navigation Menu */}
          {mobileMenuOpen && (
            <div
              id="mobile-menu"
              className="tablet:hidden border-t border-border bg-card"
            >
              <div className="space-y-1 px-4 py-3">
                <Link
                  href="/"
                  className="block min-h-touch flex items-center rounded-md px-3 py-2 text-sm font-medium text-foreground hover:bg-accent hover:text-accent-foreground transition-colors"
                  onClick={() => setMobileMenuOpen(false)}
                >
                  Home
                </Link>
                {auth?.user && (
                  <>
                    <Link
                      href="/directory"
                      className="block min-h-touch flex items-center rounded-md px-3 py-2 text-sm font-medium text-foreground hover:bg-accent hover:text-accent-foreground transition-colors"
                      onClick={() => setMobileMenuOpen(false)}
                    >
                      Directory
                    </Link>
                    <Link
                      href="/content/create"
                      className="block min-h-touch flex items-center rounded-md px-3 py-2 text-sm font-medium text-foreground hover:bg-accent hover:text-accent-foreground transition-colors"
                      onClick={() => setMobileMenuOpen(false)}
                    >
                      Create Content
                    </Link>
                    <Link
                      href="/profile"
                      className="block min-h-touch flex items-center rounded-md px-3 py-2 text-sm font-medium text-foreground hover:bg-accent hover:text-accent-foreground transition-colors"
                      onClick={() => setMobileMenuOpen(false)}
                    >
                      Profile
                    </Link>
                    <Link
                      href="/logout"
                      method="post"
                      as="button"
                      className="w-full min-h-touch flex items-center rounded-md px-3 py-2 text-sm font-medium text-destructive hover:bg-destructive/10 transition-colors"
                      onClick={() => setMobileMenuOpen(false)}
                    >
                      Logout
                    </Link>
                  </>
                )}
                {!auth?.user && (
                  <>
                    <Link
                      href="/login"
                      className="block min-h-touch flex items-center rounded-md px-3 py-2 text-sm font-medium text-foreground hover:bg-accent hover:text-accent-foreground transition-colors"
                      onClick={() => setMobileMenuOpen(false)}
                    >
                      Login
                    </Link>
                    <Link
                      href="/register"
                      className="block min-h-touch flex items-center rounded-md px-3 py-2 text-sm font-medium text-primary hover:bg-primary/10 transition-colors"
                      onClick={() => setMobileMenuOpen(false)}
                    >
                      Register
                    </Link>
                  </>
                )}
              </div>
            </div>
          )}
        </nav>

        {/* Header */}
        {header && (
          <header className="border-b border-border bg-card/50">
            <div className="mx-auto max-w-7xl px-4 py-4 tablet:px-6 desktop:px-8">
              {header}
            </div>
          </header>
        )}

        {/* Main Content */}
        <main className="mx-auto max-w-7xl px-4 py-6 tablet:px-6 desktop:px-8">
          {children}
        </main>

        {/* Footer */}
        <footer className="border-t border-border bg-card mt-auto">
          <div className="mx-auto max-w-7xl px-4 py-6 tablet:px-6 desktop:px-8">
            <p className="text-center text-sm text-muted-foreground">
              SIBAKA - Sinau Bareng Kamisetembang &copy; {new Date().getFullYear()}
            </p>
          </div>
        </footer>
      </div>
    </>
  );
}
