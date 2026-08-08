import { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Button } from '@/Components/UI/button';
import { Input } from '@/Components/UI/input';
import { Label } from '@/Components/UI/label';
import { Card } from '@/Components/UI/card';
import type { SharedPageProps } from '@/types/index.d';

interface PermissionItem {
  id: string;
  name: string;
  guard_name: string;
  roles_count?: number;
  created_at?: string;
}

interface PaginatedPermissions {
  data: PermissionItem[];
  current_page: number;
  last_page: number;
  total: number;
}

interface PermissionsIndexProps extends SharedPageProps {
  permissions: PaginatedPermissions;
  filters: {
    search?: string;
  };
}

export default function AdminPermissionsIndex() {
  const { permissions, filters } = usePage<PermissionsIndexProps>().props;

  const [search, setSearch] = useState(filters.search || '');
  const [editingPermission, setEditingPermission] = useState<PermissionItem | null>(null);
  const [showModal, setShowModal] = useState(false);

  const { data, setData, post, put, processing, errors, reset } = useForm({
    name: '',
    guard_name: 'web',
  });

  const handleOpenCreateModal = () => {
    setEditingPermission(null);
    reset();
    setShowModal(true);
  };

  const handleOpenEditModal = (permission: PermissionItem) => {
    setEditingPermission(permission);
    setData({
      name: permission.name,
      guard_name: permission.guard_name || 'web',
    });
    setShowModal(true);
  };

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    router.get('/admin/permissions', { search }, { preserveState: true });
  };

  const handleFormSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (editingPermission) {
      put(`/admin/permissions/${editingPermission.id}`, {
        onSuccess: () => {
          setShowModal(false);
          reset();
        },
      });
    } else {
      post('/admin/permissions', {
        onSuccess: () => {
          setShowModal(false);
          reset();
        },
      });
    }
  };

  const handleDelete = (id: string, name: string) => {
    if (confirm(`Delete permission '${name}'? It will be detached from all roles.`)) {
      router.delete(`/admin/permissions/${id}`);
    }
  };

  return (
    <AdminLayout title="Permission Management">
      <div className="space-y-6">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold text-foreground">Permission Management</h1>
            <p className="text-sm text-muted-foreground">
              Define granular capability permissions for Spatie RBAC role assignments.
            </p>
          </div>
          <Button onClick={handleOpenCreateModal}>
            + Create New Permission
          </Button>
        </div>

        {/* Search Filter */}
        <Card className="p-4">
          <form onSubmit={handleSearchSubmit} className="flex gap-2">
            <Input
              placeholder="Search permissions by name or guard..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="max-w-md"
            />
            <Button type="submit" variant="outline">Search</Button>
          </form>
        </Card>

        {/* Permissions Table */}
        <Card className="overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-border bg-muted/50 text-xs font-semibold uppercase text-muted-foreground">
                <tr>
                  <th className="p-4">Permission Slug / Name</th>
                  <th className="p-4">Guard</th>
                  <th className="p-4">Attached Roles Count</th>
                  <th className="p-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {permissions.data.map((permission) => (
                  <tr key={permission.id} className="hover:bg-muted/30 transition-colors">
                    <td className="p-4">
                      <span className="font-mono text-sm font-semibold text-primary bg-primary/10 px-2 py-1 rounded">
                        {permission.name}
                      </span>
                    </td>
                    <td className="p-4">
                      <span className="px-2 py-0.5 text-xs rounded bg-muted text-muted-foreground font-mono">
                        {permission.guard_name}
                      </span>
                    </td>
                    <td className="p-4 text-xs font-medium">
                      <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-secondary text-secondary-foreground font-semibold">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                        </svg>
                        {permission.roles_count ?? 0} {permission.roles_count === 1 ? 'role' : 'roles'}
                      </span>
                    </td>
                    <td className="p-4 text-right space-x-2">
                      <button
                        type="button"
                        onClick={() => handleOpenEditModal(permission)}
                        className="px-2.5 py-1 text-xs font-medium rounded border border-border hover:bg-accent"
                      >
                        Edit Name
                      </button>
                      <button
                        type="button"
                        onClick={() => handleDelete(permission.id, permission.name)}
                        className="px-2.5 py-1 text-xs font-medium rounded bg-destructive/10 text-destructive hover:bg-destructive/20"
                      >
                        Delete
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {permissions.last_page > 1 && (
            <div className="p-4 border-t border-border flex items-center justify-between">
              <span className="text-xs text-muted-foreground">
                Page {permissions.current_page} of {permissions.last_page} ({permissions.total} total permissions)
              </span>
              <div className="flex gap-2">
                {permissions.current_page > 1 && (
                  <button
                    type="button"
                    onClick={() => router.get(`/admin/permissions?page=${permissions.current_page - 1}`)}
                    className="px-3 py-1 text-xs rounded border border-border hover:bg-accent"
                  >
                    Previous
                  </button>
                )}
                {permissions.current_page < permissions.last_page && (
                  <button
                    type="button"
                    onClick={() => router.get(`/admin/permissions?page=${permissions.current_page + 1}`)}
                    className="px-3 py-1 text-xs rounded border border-border hover:bg-accent"
                  >
                    Next
                  </button>
                )}
              </div>
            </div>
          )}
        </Card>

        {/* Create / Edit Permission Modal */}
        {showModal && (
          <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <Card className="max-w-md w-full p-6 space-y-4">
              <h2 className="text-lg font-bold text-foreground">
                {editingPermission ? `Edit Permission (${editingPermission.name})` : 'Create New Permission'}
              </h2>
              <form onSubmit={handleFormSubmit} className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="name">Permission Name / Slug *</Label>
                  <Input
                    id="name"
                    placeholder="e.g. manage-learning-center, edit-own-course"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                  />
                  <p className="text-xs text-muted-foreground">
                    Spaces will automatically be converted to hyphens (e.g. <code>manage learning center</code> &rarr; <code>manage-learning-center</code>).
                  </p>
                  {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="guard_name">Guard Name *</Label>
                  <Input
                    id="guard_name"
                    value={data.guard_name}
                    onChange={(e) => setData('guard_name', e.target.value)}
                    required
                  />
                  {errors.guard_name && <p className="text-xs text-destructive">{errors.guard_name}</p>}
                </div>

                <div className="flex justify-end gap-2 pt-4 border-t border-border">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => {
                      setShowModal(false);
                      reset();
                    }}
                  >
                    Cancel
                  </Button>
                  <Button type="submit" disabled={processing}>
                    {editingPermission ? 'Save Changes' : 'Create Permission'}
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
