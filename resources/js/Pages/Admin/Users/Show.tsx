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

  const handleRoleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    router.put(`/admin/users/${userData.id}/role`, { role: selectedRole });
  };

  const handleToggleSuspension = () => {
    const action = userData.is_suspended ? 'unsuspend' : 'suspend for 7 days';
    if (confirm(`Are you sure you want to ${action} this user?`)) {
      router.post(`/admin/users/${userData.id}/toggle-suspension`, { days: 7 });
    }
  };

  return (
    <AdminLayout title={`User - ${userData.name}`}>
      <div className="space-y-6 max-w-4xl mx-auto">
        <div className="flex items-center justify-between">
          <div>
            <Link href="/admin/users" className="text-xs text-primary hover:underline flex items-center gap-1 mb-2">
              &larr; Back to Users List
            </Link>
            <h1 className="text-2xl font-bold text-foreground">{userData.name}</h1>
            <p className="text-sm text-muted-foreground">{userData.email}</p>
          </div>

          <div className="flex gap-2">
            <Button
              variant={userData.is_suspended ? 'default' : 'destructive'}
              onClick={handleToggleSuspension}
            >
              {userData.is_suspended ? 'Unsuspend User' : 'Suspend User'}
            </Button>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {/* User Profile Card */}
          <Card className="md:col-span-1">
            <CardHeader>
              <CardTitle className="text-base">Profile Overview</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4 text-sm">
              <div>
                <span className="text-xs text-muted-foreground block">Department</span>
                <span className="font-medium">{userData.department || 'N/A'}</span>
              </div>
              <div>
                <span className="text-xs text-muted-foreground block">Entry / Graduation Year</span>
                <span className="font-medium">{userData.entry_year ?? '?'} / {userData.graduation_year}</span>
              </div>
              <div>
                <span className="text-xs text-muted-foreground block">Verification Status</span>
                <span className="capitalize font-medium text-emerald-500">{userData.verification_status}</span>
              </div>
              <div>
                <span className="text-xs text-muted-foreground block">Joined Date</span>
                <span>{new Date(userData.created_at).toLocaleDateString()}</span>
              </div>

              <hr className="border-border my-4" />

              {/* Role Change Form */}
              <form onSubmit={handleRoleSubmit} className="space-y-3">
                <label className="text-xs font-semibold text-foreground block">User Role</label>
                <select
                  value={selectedRole}
                  onChange={(e) => setSelectedRole(e.target.value)}
                  className="w-full text-sm rounded border border-input bg-background p-2"
                >
                  {roles.map((r) => (
                    <option key={r} value={r} className="capitalize">{r}</option>
                  ))}
                </select>
                <Button type="submit" variant="outline" className="w-full text-xs">
                  Update Role
                </Button>
              </form>
            </CardContent>
          </Card>

          {/* User Activity & Content */}
          <Card className="md:col-span-2 space-y-6 p-6">
            <div>
              <h2 className="text-base font-semibold text-foreground mb-3">Published Posts ({userData.contents.length})</h2>
              {userData.contents.length === 0 ? (
                <p className="text-xs text-muted-foreground">No posts created yet.</p>
              ) : (
                <div className="space-y-2">
                  {userData.contents.map((content) => (
                    <div key={content.id} className="p-3 rounded border border-border flex items-center justify-between text-sm">
                      <div className="truncate max-w-[75%]">
                        <p className="font-medium text-foreground truncate">{content.title}</p>
                        <span className="text-xs text-muted-foreground capitalize">{content.category.replace('_', ' ')}</span>
                      </div>
                      <Link href={`/admin/content/${content.id}/edit`} className="text-xs text-primary hover:underline">
                        Edit Post &rarr;
                      </Link>
                    </div>
                  ))}
                </div>
              )}
            </div>

            <hr className="border-border" />

            <div>
              <h2 className="text-base font-semibold text-foreground mb-3">Warnings Received ({userData.warnings.length})</h2>
              {userData.warnings.length === 0 ? (
                <p className="text-xs text-muted-foreground">Clean record. No warnings issued.</p>
              ) : (
                <div className="space-y-2">
                  {userData.warnings.map((w) => (
                    <div key={w.id} className="p-3 rounded border border-amber-500/30 bg-amber-500/5 text-xs text-amber-600 dark:text-amber-400">
                      <p className="font-medium">{w.reason}</p>
                      <p className="text-[10px] opacity-75 mt-1">{new Date(w.created_at).toLocaleString()}</p>
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
