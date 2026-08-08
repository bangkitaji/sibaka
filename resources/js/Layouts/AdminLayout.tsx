import { type PropsWithChildren, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';

interface AdminLayoutProps extends PropsWithChildren {
  title?: string;
}

interface SubNavItem {
  name: string;
  href: string;
  icon?: React.ReactNode;
}

interface NavItem {
  name: string;
  href?: string;
  icon: React.ReactNode;
  children?: SubNavItem[];
}

interface NavGroup {
  title: string;
  items: NavItem[];
}

export default function AdminLayout({ title, children }: AdminLayoutProps) {
  const { auth, flash } = usePage<{
    auth: { user: { name: string; email: string; role: string } | null };
    flash?: { status?: string; error?: string };
  }>().props;

  const [sidebarOpen, setSidebarOpen] = useState(false);

  const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';

  const isUserMgmtActive =
    currentPath.startsWith('/admin/users') ||
    currentPath.startsWith('/admin/roles') ||
    currentPath.startsWith('/admin/permissions');

  const [userMgmtOpen, setUserMgmtOpen] = useState<boolean>(isUserMgmtActive);

  const navGroups: NavGroup[] = [
    {
      title: 'Main',
      items: [
        {
          name: 'Dashboard',
          href: '/admin/dashboard',
          icon: (
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <rect width="7" height="9" x="3" y="3" rx="1" />
              <rect width="7" height="5" x="14" y="3" rx="1" />
              <rect width="7" height="9" x="14" y="12" rx="1" />
              <rect width="7" height="5" x="3" y="16" rx="1" />
            </svg>
          ),
        },
      ],
    },
    {
      title: 'User & Access Control',
      items: [
        {
          name: 'User Management',
          icon: (
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
              <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
          ),
          children: [
            {
              name: 'Users',
              href: '/admin/users',
              icon: (
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
                  <circle cx="12" cy="7" r="4" />
                </svg>
              ),
            },
            {
              name: 'Roles',
              href: '/admin/roles',
              icon: (
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                </svg>
              ),
            },
            {
              name: 'Permissions',
              href: '/admin/permissions',
              icon: (
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4" />
                </svg>
              ),
            },
          ],
        },
        {
          name: 'Verifications Queue',
          href: '/admin/verification',
          icon: (
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
              <polyline points="22 4 12 14.01 9 11.01" />
            </svg>
          ),
        },
        {
          name: 'Invite Codes',
          href: '/admin/invite-codes',
          icon: (
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M2 18v3c0 .6.4 1 1 1h4v-3h3v-3h2l1.4-1.4a6.5 6.5 0 1 0-4-4Z" />
              <circle cx="16.5" cy="7.5" r=".5" fill="currentColor" />
            </svg>
          ),
        },
      ],
    },
    {
      title: 'Content & Academic',
      items: [
        {
          name: 'Content Management',
          href: '/admin/content',
          icon: (
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
              <polyline points="14 2 14 8 20 8" />
            </svg>
          ),
        },
        {
          name: 'Departments / Jurusan',
          href: '/admin/departments',
          icon: (
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M22 10v6M2 10l10-5 10 5-10 5z" />
              <path d="M6 12v5c3 3 9 3 12 0v-5" />
            </svg>
          ),
        },
      ],
    },
    {
      title: 'System & Configuration',
      items: [
        {
          name: 'Site Settings',
          href: '/admin/settings',
          icon: (
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.1a2 2 0 0 1-1-1.72v-.51a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z" />
              <circle cx="12" cy="12" r="3" />
            </svg>
          ),
        },
      ],
    },
  ];

  return (
    <>
      {title && <Head title={`Admin - ${title}`} />}

      <div className="min-h-screen bg-background flex flex-col tablet:flex-row text-foreground">
        {/* Mobile Header */}
        <header className="tablet:hidden flex items-center justify-between border-b border-border bg-card px-4 py-3">
          <Link href="/admin/dashboard" className="text-lg font-bold text-primary flex items-center gap-2.5">
            <img src="/images/logo.png" alt="SIBAKA Logo" className="h-8 w-auto object-contain" />
            <span>SIBAKA Admin</span>
          </Link>
          <button
            type="button"
            onClick={() => setSidebarOpen(!sidebarOpen)}
            className="p-2 text-foreground rounded-md hover:bg-accent"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <line x1="4" y1="6" x2="20" y2="6" />
              <line x1="4" y1="12" x2="20" y2="12" />
              <line x1="4" y1="18" x2="20" y2="18" />
            </svg>
          </button>
        </header>

        {/* Sidebar */}
        <aside
          className={`${
            sidebarOpen ? 'block' : 'hidden'
          } tablet:block w-full tablet:w-64 border-r border-border bg-card flex-shrink-0 flex flex-col justify-between`}
        >
          <div>
            <div className="p-5 border-b border-border hidden tablet:flex items-center justify-between">
              <Link href="/" className="text-xl font-bold text-primary flex items-center gap-2.5">
                <img src="/images/logo.png" alt="SIBAKA Logo" className="h-8 w-auto object-contain" />
                <span>SIBAKA</span>
              </Link>
            </div>

            <nav className="p-4 space-y-6">
              {navGroups.map((group) => (
                <div key={group.title} className="space-y-1.5">
                  <h3 className="px-3 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground/80">
                    {group.title}
                  </h3>
                  <div className="space-y-1">
                    {group.items.map((item) => {
                      if (item.children) {
                        const hasActiveChild = item.children.some((child) =>
                          currentPath.startsWith(child.href)
                        );
                        return (
                          <div key={item.name} className="space-y-1">
                            <button
                              type="button"
                              onClick={() => setUserMgmtOpen(!userMgmtOpen)}
                              className={`w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
                                hasActiveChild
                                  ? 'bg-primary/10 text-primary font-semibold'
                                  : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                              }`}
                            >
                              <div className="flex items-center gap-3">
                                {item.icon}
                                <span>{item.name}</span>
                              </div>
                              <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="16"
                                height="16"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2"
                                className={`transition-transform duration-200 ${
                                  userMgmtOpen ? 'rotate-90' : ''
                                }`}
                              >
                                <path d="m9 18 6-6-6-6" />
                              </svg>
                            </button>

                            {userMgmtOpen && (
                              <div className="pl-6 space-y-1">
                                {item.children.map((child) => {
                                  const isChildActive = currentPath.startsWith(child.href);
                                  return (
                                    <Link
                                      key={child.name}
                                      href={child.href}
                                      onClick={() => setSidebarOpen(false)}
                                      className={`flex items-center gap-2.5 px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${
                                        isChildActive
                                          ? 'bg-primary text-primary-foreground font-bold shadow-xs'
                                          : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                                      }`}
                                    >
                                      {child.icon}
                                      <span>{child.name}</span>
                                    </Link>
                                  );
                                })}
                              </div>
                            )}
                          </div>
                        );
                      }

                      const isActive = item.href ? currentPath.startsWith(item.href) : false;
                      return (
                        <Link
                          key={item.name}
                          href={item.href || '#'}
                          onClick={() => setSidebarOpen(false)}
                          className={`flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
                            isActive
                              ? 'bg-primary text-primary-foreground shadow-xs'
                              : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                          }`}
                        >
                          {item.icon}
                          <span>{item.name}</span>
                        </Link>
                      );
                    })}
                  </div>
                </div>
              ))}
            </nav>
          </div>

          <div className="p-4 border-t border-border space-y-2">
            <Link
              href="/"
              className="flex items-center gap-2 px-3 py-2 text-sm text-muted-foreground hover:text-foreground rounded-md transition-colors"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="m15 18-6-6 6-6" />
              </svg>
              Back to Main Portal
            </Link>
            <div className="pt-2 flex items-center gap-2 text-xs text-muted-foreground px-3">
              <span className="w-2 h-2 rounded-full bg-emerald-500" />
              Logged in as <strong className="text-foreground">{auth?.user?.name}</strong>
            </div>
          </div>
        </aside>

        {/* Main Content Area */}
        <div className="flex-1 flex flex-col min-w-0 overflow-y-auto">
          {flash?.status && (
            <div className="mx-6 mt-6 p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-sm">
              {flash.status}
            </div>
          )}
          {flash?.error && (
            <div className="mx-6 mt-6 p-4 rounded-lg bg-destructive/10 border border-destructive/30 text-destructive text-sm">
              {flash.error}
            </div>
          )}

          <main className="p-4 tablet:p-8 max-w-7xl w-full mx-auto space-y-6">
            {children}
          </main>
        </div>
      </div>
    </>
  );
}
