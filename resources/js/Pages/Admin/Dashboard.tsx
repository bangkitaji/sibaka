import { Link, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/UI/card';
import type { SharedPageProps } from '@/types/index.d';

interface Stats {
  total_users: number;
  users_by_role: {
    admin: number;
    moderator: number;
    member: number;
    pending: number;
  };
  pending_verifications: number;
  suspended_users: number;
  total_content: number;
  published_content: number;
  pending_content: number;
  total_comments: number;
  pending_reports: number;
  total_invite_codes: number;
  valid_invite_codes: number;
}

interface RecentUser {
  id: string;
  name: string;
  email: string;
  role: string;
  verification_status: string;
  created_at: string;
}

interface RecentContent {
  id: string;
  title: string;
  category: string;
  status: string;
  created_at: string;
  author?: { name: string };
}

interface DashboardProps extends SharedPageProps {
  stats: Stats;
  recentUsers: RecentUser[];
  recentContent: RecentContent[];
}

export default function AdminDashboard() {
  const { stats, recentUsers, recentContent } = usePage<DashboardProps>().props;

  return (
    <AdminLayout title="Overview">
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Admin Control Center</h1>
          <p className="text-sm text-muted-foreground">
            System overview and platform management stats.
          </p>
        </div>

        {/* Top Metric Cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <Card>
            <CardHeader className="pb-2 flex flex-row items-center justify-between">
              <CardTitle className="text-sm font-medium text-muted-foreground">Total Registered Users</CardTitle>
              <div className="p-2 bg-primary/10 rounded-lg text-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              </div>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold">{stats.total_users}</div>
              <div className="text-xs text-muted-foreground mt-1">
                {stats.users_by_role.member} members, {stats.users_by_role.moderator} mods, {stats.users_by_role.admin} admins
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2 flex flex-row items-center justify-between">
              <CardTitle className="text-sm font-medium text-muted-foreground">Pending Verifications</CardTitle>
              <div className="p-2 bg-amber-500/10 rounded-lg text-amber-500">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              </div>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-amber-500">{stats.pending_verifications}</div>
              <Link href="/admin/verification" className="text-xs text-primary hover:underline mt-1 inline-block">
                Review pending requests &rarr;
              </Link>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2 flex flex-row items-center justify-between">
              <CardTitle className="text-sm font-medium text-muted-foreground">Total Content</CardTitle>
              <div className="p-2 bg-emerald-500/10 rounded-lg text-emerald-500">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
              </div>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold">{stats.total_content}</div>
              <div className="text-xs text-muted-foreground mt-1">
                {stats.published_content} published, {stats.total_comments} comments
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2 flex flex-row items-center justify-between">
              <CardTitle className="text-sm font-medium text-muted-foreground">Invite Codes</CardTitle>
              <div className="p-2 bg-indigo-500/10 rounded-lg text-indigo-500">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M2 18v3c0 .6.4 1 1 1h4v-3h3v-3h2l1.4-1.4a6.5 6.5 0 1 0-4-4Z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/></svg>
              </div>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold">{stats.valid_invite_codes}</div>
              <Link href="/admin/invite-codes" className="text-xs text-primary hover:underline mt-1 inline-block">
                Manage codes ({stats.total_invite_codes} total) &rarr;
              </Link>
            </CardContent>
          </Card>
        </div>

        {/* Quick Management Links & Recent Tables */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Recent Registrations */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle className="text-base">Recent Users</CardTitle>
              <Link href="/admin/users" className="text-xs font-medium text-primary hover:underline">
                View all users
              </Link>
            </CardHeader>
            <CardContent>
              <div className="divide-y divide-border">
                {recentUsers.map((user) => (
                  <div key={user.id} className="py-3 flex items-center justify-between text-sm">
                    <div>
                      <p className="font-medium text-foreground">{user.name}</p>
                      <p className="text-xs text-muted-foreground">{user.email}</p>
                    </div>
                    <div className="flex items-center gap-2">
                      <span className="text-xs px-2 py-0.5 rounded bg-muted text-muted-foreground capitalize">
                        {user.role}
                      </span>
                      <Link
                        href={`/admin/users/${user.id}`}
                        className="text-xs px-2 py-1 rounded border border-border hover:bg-accent"
                      >
                        Manage
                      </Link>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>

          {/* Recent Content */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle className="text-base">Recent Content</CardTitle>
              <Link href="/admin/content" className="text-xs font-medium text-primary hover:underline">
                View all content
              </Link>
            </CardHeader>
            <CardContent>
              <div className="divide-y divide-border">
                {recentContent.map((item) => (
                  <div key={item.id} className="py-3 flex items-center justify-between text-sm">
                    <div className="max-w-[70%]">
                      <p className="font-medium text-foreground truncate">{item.title}</p>
                      <p className="text-xs text-muted-foreground">
                        by {item.author?.name ?? 'Anonymous'}
                      </p>
                    </div>
                    <div className="flex items-center gap-2">
                      <span className="text-xs px-2 py-0.5 rounded bg-primary/10 text-primary capitalize">
                        {item.category.replace('_', ' ')}
                      </span>
                      <Link
                        href={`/admin/content/${item.id}/edit`}
                        className="text-xs px-2 py-1 rounded border border-border hover:bg-accent"
                      >
                        Edit
                      </Link>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </AdminLayout>
  );
}
