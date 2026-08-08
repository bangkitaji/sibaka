import { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Button } from '@/Components/UI/button';
import { Input } from '@/Components/UI/input';
import { Label } from '@/Components/UI/label';
import { Card } from '@/Components/UI/card';
import type { SharedPageProps } from '@/types/index.d';

interface DepartmentItem {
  id: string;
  code: string;
  name: string;
  description: string | null;
  is_active: boolean;
  sort_order: number;
}

interface PaginatedDepartments {
  data: DepartmentItem[];
  current_page: number;
  last_page: number;
  total: number;
}

interface DepartmentsIndexProps extends SharedPageProps {
  departments: PaginatedDepartments;
  filters: {
    search?: string;
  };
}

export default function AdminDepartmentsIndex() {
  const { departments, filters } = usePage<DepartmentsIndexProps>().props;

  const [search, setSearch] = useState(filters.search || '');
  const [editingDepartment, setEditingDepartment] = useState<DepartmentItem | null>(null);
  const [showModal, setShowModal] = useState(false);

  const { data, setData, post, put, processing, errors, reset } = useForm({
    code: '',
    name: '',
    description: '',
    sort_order: 0,
    is_active: true,
  });

  const handleOpenCreateModal = () => {
    setEditingDepartment(null);
    reset();
    setShowModal(true);
  };

  const handleOpenEditModal = (dept: DepartmentItem) => {
    setEditingDepartment(dept);
    setData({
      code: dept.code,
      name: dept.name,
      description: dept.description || '',
      sort_order: dept.sort_order,
      is_active: dept.is_active,
    });
    setShowModal(true);
  };

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    router.get('/admin/departments', { search }, { preserveState: true });
  };

  const handleFormSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (editingDepartment) {
      put(`/admin/departments/${editingDepartment.id}`, {
        onSuccess: () => {
          setShowModal(false);
          reset();
        },
      });
    } else {
      post('/admin/departments', {
        onSuccess: () => {
          setShowModal(false);
          reset();
        },
      });
    }
  };

  const handleToggleActive = (id: string, code: string, isActive: boolean) => {
    const action = isActive ? 'deactivate' : 'activate';
    if (confirm(`Are you sure you want to ${action} department ${code}?`)) {
      router.post(`/admin/departments/${id}/toggle-active`);
    }
  };

  const handleDelete = (id: string, code: string) => {
    if (confirm(`Delete department ${code}? This cannot be undone.`)) {
      router.delete(`/admin/departments/${id}`);
    }
  };

  return (
    <AdminLayout title="Departments">
      <div className="space-y-6">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold text-foreground">Department Management</h1>
            <p className="text-sm text-muted-foreground">
              Manage study majors/departments referenced during user registration.
            </p>
          </div>
          <Button onClick={handleOpenCreateModal}>
            + Add New Department
          </Button>
        </div>

        {/* Filter */}
        <Card className="p-4">
          <form onSubmit={handleSearchSubmit} className="flex gap-2">
            <Input
              placeholder="Search by code, name, or description..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="max-w-md"
            />
            <Button type="submit" variant="outline">Search</Button>
          </form>
        </Card>

        {/* Departments Table */}
        <Card className="overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-border bg-muted/50 text-xs font-semibold uppercase text-muted-foreground">
                <tr>
                  <th className="p-4">Order</th>
                  <th className="p-4">Code</th>
                  <th className="p-4">Department Name</th>
                  <th className="p-4">Description</th>
                  <th className="p-4">Status</th>
                  <th className="p-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {departments.data.map((dept) => (
                  <tr key={dept.id} className="hover:bg-muted/30 transition-colors">
                    <td className="p-4 text-xs font-mono font-bold text-muted-foreground">
                      #{dept.sort_order}
                    </td>
                    <td className="p-4">
                      <span className="font-mono text-sm font-bold tracking-wider text-primary bg-primary/10 px-2 py-1 rounded">
                        {dept.code}
                      </span>
                    </td>
                    <td className="p-4 font-medium text-foreground">
                      {dept.name}
                    </td>
                    <td className="p-4 text-xs text-muted-foreground max-w-xs truncate">
                      {dept.description || '-'}
                    </td>
                    <td className="p-4">
                      {dept.is_active ? (
                        <span className="px-2 py-0.5 text-xs rounded bg-emerald-500/10 text-emerald-500 font-medium">
                          Active
                        </span>
                      ) : (
                        <span className="px-2 py-0.5 text-xs rounded bg-muted text-muted-foreground font-medium">
                          Inactive
                        </span>
                      )}
                    </td>
                    <td className="p-4 text-right space-x-2">
                      <button
                        type="button"
                        onClick={() => handleOpenEditModal(dept)}
                        className="px-2.5 py-1 text-xs font-medium rounded border border-border hover:bg-accent"
                      >
                        Edit
                      </button>
                      <button
                        type="button"
                        onClick={() => handleToggleActive(dept.id, dept.code, dept.is_active)}
                        className={`px-2.5 py-1 text-xs font-medium rounded transition-colors ${
                          dept.is_active
                            ? 'bg-amber-500/10 text-amber-600 hover:bg-amber-500/20'
                            : 'bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20'
                        }`}
                      >
                        {dept.is_active ? 'Deactivate' : 'Activate'}
                      </button>
                      <button
                        type="button"
                        onClick={() => handleDelete(dept.id, dept.code)}
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
          {departments.last_page > 1 && (
            <div className="p-4 border-t border-border flex items-center justify-between">
              <span className="text-xs text-muted-foreground">
                Page {departments.current_page} of {departments.last_page}
              </span>
              <div className="flex gap-2">
                {departments.current_page > 1 && (
                  <button
                    type="button"
                    onClick={() => router.get(`/admin/departments?page=${departments.current_page - 1}`)}
                    className="px-3 py-1 text-xs rounded border border-border hover:bg-accent"
                  >
                    Previous
                  </button>
                )}
                {departments.current_page < departments.last_page && (
                  <button
                    type="button"
                    onClick={() => router.get(`/admin/departments?page=${departments.current_page + 1}`)}
                    className="px-3 py-1 text-xs rounded border border-border hover:bg-accent"
                  >
                    Next
                  </button>
                )}
              </div>
            </div>
          )}
        </Card>

        {/* Create / Edit Department Modal */}
        {showModal && (
          <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <Card className="max-w-lg w-full p-6 space-y-4">
              <h2 className="text-lg font-bold text-foreground">
                {editingDepartment ? `Edit Department (${editingDepartment.code})` : 'Create New Department'}
              </h2>
              <form onSubmit={handleFormSubmit} className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label htmlFor="code">Department Code *</Label>
                    <Input
                      id="code"
                      placeholder="e.g. SIJA, TME, TEI"
                      value={data.code}
                      onChange={(e) => setData('code', e.target.value)}
                      required
                    />
                    {errors.code && <p className="text-xs text-destructive">{errors.code}</p>}
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="sort_order">Display Sort Order *</Label>
                    <Input
                      id="sort_order"
                      type="number"
                      min={0}
                      value={data.sort_order}
                      onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)}
                      required
                    />
                    {errors.sort_order && <p className="text-xs text-destructive">{errors.sort_order}</p>}
                  </div>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="name">Full Department Name *</Label>
                  <Input
                    id="name"
                    placeholder="e.g. Sistem Informasi, Jaringan dan Aplikasi"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                  />
                  {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="description">Description (Optional)</Label>
                  <textarea
                    id="description"
                    rows={3}
                    placeholder="Brief description of major specialization..."
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    className="w-full rounded-md border border-input bg-background p-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                  />
                  {errors.description && <p className="text-xs text-destructive">{errors.description}</p>}
                </div>

                <div className="pt-2">
                  <label className="flex items-center gap-2 cursor-pointer text-sm font-medium">
                    <input
                      type="checkbox"
                      checked={data.is_active}
                      onChange={(e) => setData('is_active', e.target.checked)}
                      className="h-4 w-4 rounded border-input text-primary"
                    />
                    <span>Active (visible in registration dropdown)</span>
                  </label>
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
                    {editingDepartment ? 'Save Changes' : 'Create Department'}
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
