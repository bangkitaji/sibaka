import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Button } from '@/Components/UI/button';
import { Input } from '@/Components/UI/input';
import { Card } from '@/Components/UI/card';
import type { SharedPageProps } from '@/types/index.d';

interface UserItem {
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
}

interface PaginatedUsers {
  data: UserItem[];
  current_page: number;
  last_page: number;
  total: number;
}

interface UsersIndexProps extends SharedPageProps {
  users: PaginatedUsers;
  filters: {
    search?: string;
    role?: string;
    verification_status?: string;
    is_suspended?: string;
  };
  roles: string[];
  verificationStatuses: string[];
}

export default function AdminUsersIndex() {
  const { users, filters, roles, verificationStatuses } = usePage<UsersIndexProps>().props;

  const [search, setSearch] = useState(filters.search || '');
  const [selectedRole, setSelectedRole] = useState(filters.role || '');
  const [selectedStatus, setSelectedStatus] = useState(filters.verification_status || '');
  const [suspendedFilter, setSuspendedFilter] = useState(filters.is_suspended || '');

  const handleFilter = (e: React.FormEvent) => {
    e.preventDefault();
    router.get('/admin/users', {
      search,
      role: selectedRole,
      verification_status: selectedStatus,
      is_suspended: suspendedFilter,
    }, { preserveState: true });
  };

  const handleToggleSuspension = (userId: string, isSuspended: boolean) => {
    const action = isSuspended ? 'unsuspend' : 'suspend for 7 days';
    if (confirm(`Are you sure you want to ${action} this user?`)) {
      router.post(`/admin/users/${userId}/toggle-suspension`, { days: 7 });
    }
  };

  return (
    <AdminLayout title="User Management">
      <div className="space-y-6">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold text-foreground">User Management</h1>
            <p className="text-sm text-muted-foreground">
              Total {users.total} registered users found.
            </p>
          </div>
        </div>

        {/* Filter Bar */}
        <Card className="p-4">
          <form onSubmit={handleFilter} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <Input
              placeholder="Search by name, email, department..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />

            <select
              value={selectedRole}
              onChange={(e) => setSelectedRole(e.target.value)}
              className="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
            >
              <option value="">All Roles</option>
              {roles.map((r) => (
                <option key={r} value={r} className="capitalize">{r}</option>
              ))}
            </select>

            <select
              value={selectedStatus}
              onChange={(e) => setSelectedStatus(e.target.value)}
              className="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
            >
              <option value="">All Verification Statuses</option>
              {verificationStatuses.map((s) => (
                <option key={s} value={s} className="capitalize">{s}</option>
              ))}
            </select>

            <select
              value={suspendedFilter}
              onChange={(e) => setSuspendedFilter(e.target.value)}
              className="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
            >
              <option value="">All Account States</option>
              <option value="false">Active Only</option>
              <option value="true">Suspended Only</option>
            </select>

            <Button type="submit" variant="default">Filter</Button>
          </form>
        </Card>

        {/* Users Table */}
        <Card className="overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-border bg-muted/50 text-xs font-semibold uppercase text-muted-foreground">
                <tr>
                  <th className="p-4">Name & Email</th>
                  <th className="p-4">Department / Entry Year</th>
                  <th className="p-4">Verification</th>
                  <th className="p-4">Role</th>
                  <th className="p-4">Status</th>
                  <th className="p-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {users.data.map((user) => (
                  <tr key={user.id} className="hover:bg-muted/30 transition-colors">
                    <td className="p-4">
                      <div className="font-medium text-foreground">{user.name}</div>
                      <div className="text-xs text-muted-foreground">{user.email}</div>
                    </td>
                    <td className="p-4 text-xs">
                      <div className="font-medium">{user.department || '-'}</div>
                      <div className="text-muted-foreground font-mono">{user.entry_year || '-'}</div>
                    </td>
                    <td className="p-4">
                      <span
                        className={`inline-block px-2 py-0.5 text-xs rounded font-medium capitalize ${
                          user.verification_status === 'approved'
                            ? 'bg-emerald-500/10 text-emerald-500'
                            : user.verification_status === 'pending'
                            ? 'bg-amber-500/10 text-amber-500'
                            : 'bg-destructive/10 text-destructive'
                        }`}
                      >
                        {user.verification_status}
                      </span>
                    </td>
                    <td className="p-4">
                      <div className="flex flex-wrap gap-1">
                        {user.roles && user.roles.length > 0 ? (
                          user.roles.map((r) => (
                            <span
                              key={r.name}
                              className={`px-2 py-0.5 text-xs rounded font-medium capitalize font-mono ${
                                r.name === 'super-admin' || r.name === 'admin'
                                  ? 'bg-purple-500/10 text-purple-600 border border-purple-500/30 font-semibold'
                                  : r.name === 'instructor'
                                  ? 'bg-blue-500/10 text-blue-600 border border-blue-500/30 font-semibold'
                                  : r.name === 'moderator'
                                  ? 'bg-amber-500/10 text-amber-600 border border-amber-500/30 font-semibold'
                                  : 'bg-muted text-muted-foreground border border-border'
                              }`}
                            >
                              {r.name}
                            </span>
                          ))
                        ) : (
                          <span className="px-2 py-0.5 text-xs rounded bg-muted text-muted-foreground font-medium capitalize font-mono border border-border">
                            {user.role || 'member'}
                          </span>
                        )}
                      </div>
                    </td>
                    <td className="p-4">
                      {user.is_suspended ? (
                        <span className="px-2 py-0.5 text-xs rounded bg-destructive/10 text-destructive font-medium">
                          Suspended
                        </span>
                      ) : (
                        <span className="px-2 py-0.5 text-xs rounded bg-emerald-500/10 text-emerald-500 font-medium">
                          Active
                        </span>
                      )}
                    </td>
                    <td className="p-4 text-right space-x-2">
                      <Link
                        href={`/admin/users/${user.id}`}
                        className="inline-block px-2.5 py-1 text-xs font-medium rounded border border-border hover:bg-accent"
                      >
                        Details
                      </Link>
                      <button
                        type="button"
                        onClick={() => handleToggleSuspension(user.id, user.is_suspended)}
                        className={`px-2.5 py-1 text-xs font-medium rounded transition-colors ${
                          user.is_suspended
                            ? 'bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20'
                            : 'bg-destructive/10 text-destructive hover:bg-destructive/20'
                        }`}
                      >
                        {user.is_suspended ? 'Unsuspend' : 'Suspend'}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {users.last_page > 1 && (
            <div className="p-4 border-t border-border flex items-center justify-between">
              <span className="text-xs text-muted-foreground">
                Page {users.current_page} of {users.last_page}
              </span>
              <div className="flex gap-2">
                {users.current_page > 1 && (
                  <Link
                    href={`/admin/users?page=${users.current_page - 1}`}
                    className="px-3 py-1 text-xs rounded border border-border hover:bg-accent"
                  >
                    Previous
                  </Link>
                )}
                {users.current_page < users.last_page && (
                  <Link
                    href={`/admin/users?page=${users.current_page + 1}`}
                    className="px-3 py-1 text-xs rounded border border-border hover:bg-accent"
                  >
                    Next
                  </Link>
                )}
              </div>
            </div>
          )}
        </Card>
      </div>
    </AdminLayout>
  );
}
