import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/UI/card';
import { Button } from '@/Components/UI/button';
import type { SharedPageProps } from '@/types/index.d';

interface ContentItem {
  id: string;
  title: string;
  category: string;
  status: string;
  created_at: string;
}

interface WarningItem {
  id: string;
  reason: string;
  created_at: string;
}

interface UserDetail {
  id: string;
  name: string;
  email: string;
  department: string;
  entry_year: number;
  graduation_year: number;
  role: string;
  roles?: { id: number; name: string }[];
  verification_status: string;
  is_suspended: boolean;
  suspended_until: string | null;
  created_at: string;
  contents: ContentItem[];
  warnings: WarningItem[];
}

interface UserShowProps extends SharedPageProps {
  userData: UserDetail;
  roles: string[];
  verificationStatuses: string[];
}

export default function AdminUserShow() {
  const { userData, roles } = usePage<UserShowProps>().props;
  const [selectedRole, setSelectedRole] = useState(userData.role);

  const handleToggleSuspension = () => {
    const action = userData.is_suspended ? 'unsuspend' : 'suspend for 7 days';
    if (confirm(`Are you sure you want to ${action} this user?`)) {
      router.post(`/admin/users/${userData.id}/toggle-suspension`, { days: 7 });
    }
  };

  return (
    <AdminLayout title={`User Detail - ${userData.name}`}>
      <div className="space-y-8 w-full">
        {/* Top Header Navigation */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-border pb-6">
          <div className="space-y-1">
            <Link
              href="/admin/users"
              className="text-xs font-semibold text-primary hover:underline inline-flex items-center gap-1.5 mb-1"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="m15 18-6-6 6-6" />
              </svg>
              Back to Users List
            </Link>
            <div className="flex items-center gap-3">
              <h1 className="text-3xl font-bold text-foreground tracking-tight">{userData.name}</h1>
              {userData.is_suspended && (
                <span className="px-2.5 py-1 text-xs rounded-full bg-destructive/10 text-destructive font-semibold">
                  Suspended
                </span>
              )}
            </div>
            <p className="text-base text-muted-foreground">{userData.email}</p>
          </div>

          <div className="flex items-center gap-3">
            <Button
              variant={userData.is_suspended ? 'default' : 'destructive'}
              onClick={handleToggleSuspension}
              className="px-5 py-2.5 text-sm font-semibold"
            >
              {userData.is_suspended ? 'Unsuspend Account' : 'Suspend Account (7 Days)'}
            </Button>
          </div>
        </div>

        {/* Enlarged Cards Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Left Column: User Profile Overview Card */}
          <Card className="lg:col-span-1 p-6 space-y-6 shadow-sm">
            <CardHeader className="p-0 border-b border-border pb-4">
              <CardTitle className="text-lg font-bold flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="text-primary">
                  <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
                  <circle cx="12" cy="7" r="4" />
                </svg>
                Profile Details
              </CardTitle>
            </CardHeader>
            <CardContent className="p-0 space-y-5 text-sm">
              <div className="grid grid-cols-1 gap-4">
                <div className="p-3.5 rounded-lg bg-muted/40 border border-border/50">
                  <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground block mb-1">
                    Department / Jurusan
                  </span>
                  <span className="font-bold text-base text-foreground">
                    {userData.department || 'N/A'}
                  </span>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div className="p-3.5 rounded-lg bg-muted/40 border border-border/50">
                    <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground block mb-1">
                      Year of Entry
                    </span>
                    <span className="font-bold text-base text-foreground">
                      {userData.entry_year || 'N/A'}
                    </span>
                  </div>

                  <div className="p-3.5 rounded-lg bg-muted/40 border border-border/50">
                    <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground block mb-1">
                      Year of Graduation
                    </span>
                    <span className="font-bold text-base text-foreground">
                      {userData.graduation_year || 'N/A'}
                    </span>
                  </div>
                </div>

                <div className="p-3.5 rounded-lg bg-muted/40 border border-border/50">
                  <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground block mb-1">
                    Verification Status
                  </span>
                  <span className={`inline-block px-2.5 py-1 text-xs rounded-md font-bold uppercase tracking-wider ${
                    userData.verification_status === 'approved'
                      ? 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/30'
                      : userData.verification_status === 'pending'
                      ? 'bg-amber-500/10 text-amber-600 border border-amber-500/30'
                      : 'bg-destructive/10 text-destructive border border-destructive/30'
                  }`}>
                    {userData.verification_status}
                  </span>
                </div>

                <div className="p-3.5 rounded-lg bg-muted/40 border border-border/50">
                  <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground block mb-1">
                    Joined Date
                  </span>
                  <span className="font-bold text-base text-foreground">
                    {new Date(userData.created_at).toLocaleDateString(undefined, {
                      year: 'numeric',
                      month: 'long',
                      day: 'numeric',
                    })}
                  </span>
                </div>
              </div>

              {/* Spatie Roles Badge Display */}
              <div className="pt-2">
                <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground block mb-2">
                  Assigned Spatie Roles
                </span>
                <div className="flex flex-wrap gap-1.5">
                  {userData.roles && userData.roles.length > 0 ? (
                    userData.roles.map((r) => (
                      <span
                        key={r.name}
                        className="px-3 py-1 text-xs rounded-md font-bold font-mono capitalize bg-primary/10 text-primary border border-primary/20"
                      >
                        {r.name}
                      </span>
                    ))
                  ) : (
                    <span className="px-3 py-1 text-xs rounded-md font-bold font-mono capitalize bg-muted text-muted-foreground border border-border">
                      {userData.role || 'member'}
                    </span>
                  )}
                </div>
              </div>

              <hr className="border-border my-4" />

              {/* Primary Role Update Section */}
              <div className="space-y-3">
                <label className="text-xs font-bold text-foreground block uppercase tracking-wider">
                  Update Primary Role Attribute
                </label>
                <div className="flex flex-wrap gap-2">
                  {roles.map((r) => {
                    const isSelected = selectedRole === r;
                    return (
                      <button
                        key={r}
                        type="button"
                        onClick={() => {
                          setSelectedRole(r);
                          router.put(`/admin/users/${userData.id}/role`, { role: r }, { preserveState: true });
                        }}
                        className={`px-3 py-1.5 text-xs rounded-lg font-bold capitalize transition-all border flex items-center gap-1.5 ${
                          isSelected
                            ? 'bg-primary text-primary-foreground border-primary shadow-xs ring-2 ring-primary/20'
                            : 'bg-background text-muted-foreground border-input hover:border-primary/50 hover:text-foreground'
                        }`}
                      >
                        {isSelected && (
                          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3">
                            <polyline points="20 6 9 17 4 12" />
                          </svg>
                        )}
                        <span>{r}</span>
                      </button>
                    );
                  })}
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Right Column: Content & Activity Card */}
          <Card className="lg:col-span-2 p-8 space-y-8 shadow-sm">
            {/* Published Content Section */}
            <div className="space-y-4">
              <div className="flex items-center justify-between border-b border-border pb-3">
                <h2 className="text-lg font-bold text-foreground flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="text-primary">
                    <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
                    <polyline points="14 2 14 8 20 8" />
                  </svg>
                  Published Articles & Posts
                </h2>
                <span className="text-xs font-mono font-bold px-2.5 py-1 rounded-full bg-primary/10 text-primary">
                  {userData.contents.length} {userData.contents.length === 1 ? 'post' : 'posts'}
                </span>
              </div>

              {userData.contents.length === 0 ? (
                <div className="p-8 text-center border border-dashed border-border rounded-lg bg-muted/20">
                  <p className="text-sm text-muted-foreground">No published articles or forum posts created by this user yet.</p>
                </div>
              ) : (
                <div className="space-y-3">
                  {userData.contents.map((content) => (
                    <div
                      key={content.id}
                      className="p-4 rounded-lg border border-border bg-card hover:bg-accent/40 transition-colors flex items-center justify-between gap-4"
                    >
                      <div className="space-y-1 truncate">
                        <p className="font-semibold text-foreground text-base truncate">{content.title}</p>
                        <div className="flex items-center gap-3 text-xs text-muted-foreground">
                          <span className="capitalize px-2 py-0.5 rounded bg-muted font-medium">
                            {content.category.replace('_', ' ')}
                          </span>
                          <span>&middot;</span>
                          <span>{new Date(content.created_at).toLocaleDateString()}</span>
                        </div>
                      </div>
                      <Link
                        href={`/admin/content/${content.id}/edit`}
                        className="px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary/10 rounded-md border border-primary/20 transition-colors flex-shrink-0"
                      >
                        Edit Post &rarr;
                      </Link>
                    </div>
                  ))}
                </div>
              )}
            </div>

            {/* Warnings & Moderation History Section */}
            <div className="space-y-4 pt-4 border-t border-border">
              <div className="flex items-center justify-between border-b border-border pb-3">
                <h2 className="text-lg font-bold text-foreground flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="text-amber-500">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                    <line x1="12" y1="9" x2="12" y2="13" />
                    <line x1="12" y1="17" x2="12.01" y2="17" />
                  </svg>
                  Warnings & Moderation History
                </h2>
                <span className={`text-xs font-mono font-bold px-2.5 py-1 rounded-full ${
                  userData.warnings.length > 0 ? 'bg-amber-500/10 text-amber-600' : 'bg-emerald-500/10 text-emerald-600'
                }`}>
                  {userData.warnings.length} {userData.warnings.length === 1 ? 'warning' : 'warnings'}
                </span>
              </div>

              {userData.warnings.length === 0 ? (
                <div className="p-6 text-center border border-emerald-500/20 rounded-lg bg-emerald-500/5">
                  <p className="text-sm font-medium text-emerald-600 dark:text-emerald-400">
                    Clean Record &mdash; No warnings or moderation strikes issued.
                  </p>
                </div>
              ) : (
                <div className="space-y-3">
                  {userData.warnings.map((w) => (
                    <div
                      key={w.id}
                      className="p-4 rounded-lg border border-amber-500/30 bg-amber-500/5 text-amber-700 dark:text-amber-300 space-y-1"
                    >
                      <p className="text-sm font-semibold">{w.reason}</p>
                      <p className="text-xs opacity-80">{new Date(w.created_at).toLocaleString()}</p>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </Card>
        </div>
      </div>
    </AdminLayout>
  );
}
