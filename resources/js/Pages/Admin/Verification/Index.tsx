import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Button } from '@/Components/UI/button';
import { Card } from '@/Components/UI/card';
import type { SharedPageProps } from '@/types/index.d';

interface UserVerificationItem {
  id: string;
  name: string;
  email: string;
  department: string;
  entry_year: number;
  graduation_year: number;
  verification_status: string;
  created_at: string;
}

interface PaginatedUsers {
  data: UserVerificationItem[];
  current_page: number;
  last_page: number;
  total: number;
}

interface VerificationIndexProps extends SharedPageProps {
  users: PaginatedUsers;
  currentStatus: string;
  statuses: string[];
}

export default function AdminVerificationIndex() {
  const { users, currentStatus, statuses } = usePage<VerificationIndexProps>().props;
  const [rejectingUserId, setRejectingUserId] = useState<string | null>(null);
  const [rejectReason, setRejectReason] = useState('');

  const handleStatusFilter = (status: string) => {
    router.get('/admin/verification', { status }, { preserveState: true });
  };

  const handleApprove = (userId: string) => {
    if (confirm('Approve verification for this member?')) {
      router.post(`/admin/verification/${userId}/approve`);
    }
  };

  const handleRejectSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!rejectingUserId || !rejectReason.trim()) return;

    router.post(`/admin/verification/${rejectingUserId}/reject`, {
      reason: rejectReason,
    }, {
      onSuccess: () => {
        setRejectingUserId(null);
        setRejectReason('');
      },
    });
  };

  return (
    <AdminLayout title="User Verifications">
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Member Verification Management</h1>
          <p className="text-sm text-muted-foreground">
            Review and process pending alumni account verifications.
          </p>
        </div>

        {/* Status Tabs */}
        <div className="flex gap-2 border-b border-border pb-3">
          {statuses.map((status) => (
            <button
              key={status}
              onClick={() => handleStatusFilter(status)}
              className={`px-4 py-2 text-sm font-medium rounded-lg capitalize transition-colors ${
                currentStatus === status
                  ? 'bg-primary text-primary-foreground'
                  : 'bg-muted/50 text-muted-foreground hover:bg-accent hover:text-foreground'
              }`}
            >
              {status} ({status === currentStatus ? users.total : '...'})
            </button>
          ))}
        </div>

        {/* Verifications Table */}
        <Card className="overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-border bg-muted/50 text-xs font-semibold uppercase text-muted-foreground">
                <tr>
                  <th className="p-4">Alumni Name & Email</th>
                  <th className="p-4">Department</th>
                  <th className="p-4">Graduation / Entry Year</th>
                  <th className="p-4">Submitted Date</th>
                  <th className="p-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {users.data.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="p-8 text-center text-sm text-muted-foreground">
                      No verification requests found for status &quot;{currentStatus}&quot;.
                    </td>
                  </tr>
                ) : (
                  users.data.map((user) => (
                    <tr key={user.id} className="hover:bg-muted/30 transition-colors">
                      <td className="p-4">
                        <div className="font-medium text-foreground">{user.name}</div>
                        <div className="text-xs text-muted-foreground">{user.email}</div>
                      </td>
                      <td className="p-4 text-xs font-medium">{user.department || '-'}</td>
                      <td className="p-4 text-xs">
                        Class of {user.graduation_year} {user.entry_year ? `(Entry: ${user.entry_year})` : ''}
                      </td>
                      <td className="p-4 text-xs text-muted-foreground">
                        {new Date(user.created_at).toLocaleDateString()}
                      </td>
                      <td className="p-4 text-right space-x-2">
                        {user.verification_status === 'pending' && (
                          <>
                            <Button
                              size="sm"
                              onClick={() => handleApprove(user.id)}
                              className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs"
                            >
                              Approve
                            </Button>
                            <Button
                              size="sm"
                              variant="destructive"
                              onClick={() => setRejectingUserId(user.id)}
                              className="text-xs"
                            >
                              Reject
                            </Button>
                          </>
                        )}
                        {user.verification_status !== 'pending' && (
                          <span className="text-xs font-medium capitalize text-muted-foreground">
                            {user.verification_status}
                          </span>
                        )}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </Card>

        {/* Reject Reason Modal / Form */}
        {rejectingUserId && (
          <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <Card className="max-w-md w-full p-6 space-y-4">
              <h2 className="text-lg font-bold text-foreground">Reject Verification</h2>
              <p className="text-xs text-muted-foreground">
                Please specify a reason for rejecting this verification request. This will be sent to the user.
              </p>
              <form onSubmit={handleRejectSubmit} className="space-y-4">
                <textarea
                  rows={4}
                  value={rejectReason}
                  onChange={(e) => setRejectReason(e.target.value)}
                  placeholder="e.g. Invalid graduation year or department info provided..."
                  className="w-full rounded-md border border-input bg-background p-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                  required
                />
                <div className="flex justify-end gap-2">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => {
                      setRejectingUserId(null);
                      setRejectReason('');
                    }}
                  >
                    Cancel
                  </Button>
                  <Button type="submit" variant="destructive">
                    Confirm Rejection
                  </Button>
                </div>
              </form>
            </Card>
          </div>
        )}
      </div>
    </AdminLayout>
  );
}
