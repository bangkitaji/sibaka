import { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Button } from '@/Components/UI/button';
import { Input } from '@/Components/UI/input';
import { Label } from '@/Components/UI/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/UI/card';
import type { SharedPageProps } from '@/types/index.d';

interface PermissionItem {
  id: number;
  name: string;
}

interface RoleItem {
  id: number;
  name: string;
  permissions: PermissionItem[];
}

interface UserWithRoles {
  id: string;
  name: string;
  email: string;
  roles: { id: number; name: string }[];
}

interface PaginatedUsers {
  data: UserWithRoles[];
  current_page: number;
  last_page: number;
  total: number;
}

interface RolesIndexProps extends SharedPageProps {
  roles: RoleItem[];
  permissions: PermissionItem[];
  users: PaginatedUsers;
  filters: {
    search?: string;
  };
}

export default function AdminRolesIndex() {
  const { roles, permissions, users, filters } = usePage<RolesIndexProps>().props;

  const [selectedRole, setSelectedRole] = useState<RoleItem>(roles[0] || null);
  const [rolePermissions, setRolePermissions] = useState<string[]>(
    roles[0]?.permissions.map((p) => p.name) || []
  );
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [searchUser, setSearchUser] = useState(filters.search || '');

  // Form for creating new role
  const createForm = useForm({
    name: '',
    permissions: [] as string[],
  });

  const handleSelectRole = (role: RoleItem) => {
    setSelectedRole(role);
    setRolePermissions(role.permissions.map((p) => p.name));
  };

  const handlePermissionToggle = (permissionName: string) => {
    if (rolePermissions.includes(permissionName)) {
      setRolePermissions(rolePermissions.filter((p) => p !== permissionName));
    } else {
      setRolePermissions([...rolePermissions, permissionName]);
    }
  };

  const handleSaveRolePermissions = () => {
    if (!selectedRole) return;
    router.put(`/admin/roles/${selectedRole.id}/permissions`, {
      permissions: rolePermissions,
    });
  };

  const handleCreateRoleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    createForm.post('/admin/roles', {
      onSuccess: () => {
        setShowCreateModal(false);
        createForm.reset();
      },
    });
  };

  const handleUserRoleToggle = (userId: string, currentRoles: string[], roleName: string) => {
    let updatedRoles: string[];
    if (currentRoles.includes(roleName)) {
      updatedRoles = currentRoles.filter((r) => r !== roleName);
    } else {
      updatedRoles = [...currentRoles, roleName];
    }

    if (updatedRoles.length === 0) {
      alert('A user must have at least one role.');
      return;
    }

    router.put(`/admin/users/${userId}/spatie-roles`, {
      roles: updatedRoles,
    }, { preserveState: true });
  };

  const handleUserSearch = (e: React.FormEvent) => {
    e.preventDefault();
    router.get('/admin/roles', { search: searchUser }, { preserveState: true });
  };

  return (
    <AdminLayout title="Roles & Permissions">
      <div className="space-y-8">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold text-foreground">Role & Permission Management (RBAC)</h1>
            <p className="text-sm text-muted-foreground">
              Configure dynamic role permissions and assign instructor/member roles for Learning Center.
            </p>
          </div>
          <Button onClick={() => setShowCreateModal(true)}>
            + Create New Custom Role
          </Button>
        </div>

        {/* Section 1: Role Permissions Matrix */}
        <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
          {/* Roles Selector Tabs */}
          <Card className="lg:col-span-1 p-4 space-y-2">
            <h2 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">
              Select Role to Configure
            </h2>
            <div className="space-y-1">
              {roles.map((r) => {
                const isSelected = selectedRole?.id === r.id;
                return (
                  <button
                    key={r.id}
                    type="button"
                    onClick={() => handleSelectRole(r)}
                    className={`w-full text-left px-3 py-2.5 rounded-lg text-sm font-medium capitalize flex items-center justify-between transition-colors ${
                      isSelected
                        ? 'bg-primary text-primary-foreground'
                        : 'hover:bg-accent text-foreground'
                    }`}
                  >
                    <span>{r.name}</span>
                    <span className="text-xs opacity-75">{r.permissions.length} perms</span>
                  </button>
                );
              })}
            </div>
          </Card>

          {/* Permissions Matrix for Selected Role */}
          <Card className="lg:col-span-3">
            <CardHeader className="flex flex-row items-center justify-between">
              <div>
                <CardTitle className="text-lg capitalize">
                  Permissions for Role: <span className="text-primary">{selectedRole?.name}</span>
                </CardTitle>
                <p className="text-xs text-muted-foreground mt-1">
                  Check or uncheck granular permissions to dynamically adjust access rights.
                </p>
              </div>
              <Button onClick={handleSaveRolePermissions} size="sm">
                Save Matrix Changes
              </Button>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {permissions.map((perm) => {
                  const isChecked = rolePermissions.includes(perm.name);
                  const isSuperAdmin = selectedRole?.name === 'super-admin';

                  return (
                    <label
                      key={perm.id}
                      className={`flex items-start gap-3 p-3 rounded-lg border transition-colors ${
                        isChecked
                          ? 'border-primary/50 bg-primary/5'
                          : 'border-border bg-card'
                      } ${isSuperAdmin ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer'}`}
                    >
                      <input
                        type="checkbox"
                        checked={isChecked}
                        disabled={isSuperAdmin}
                        onChange={() => handlePermissionToggle(perm.name)}
                        className="mt-0.5 h-4 w-4 rounded border-input text-primary focus:ring-ring"
                      />
                      <div>
                        <span className="text-sm font-mono font-semibold text-foreground block">
                          {perm.name}
                        </span>
                        <span className="text-xs text-muted-foreground capitalize">
                          {perm.name.replace(/-/g, ' ')}
                        </span>
                      </div>
                    </label>
                  );
                })}
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Section 2: User Role Assignment Table */}
        <Card className="p-6 space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <h2 className="text-lg font-bold text-foreground">Multi-Role User Assignments</h2>
              <p className="text-xs text-muted-foreground">
                Assign roles to users (e.g. member + instructor / pemateri).
              </p>
            </div>

            <form onSubmit={handleUserSearch} className="flex gap-2">
              <Input
                placeholder="Search user..."
                value={searchUser}
                onChange={(e) => setSearchUser(e.target.value)}
                className="w-48 text-sm"
              />
              <Button type="submit" size="sm" variant="outline">Search</Button>
            </form>
          </div>

          <div className="overflow-x-auto border rounded-lg border-border">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-border bg-muted/50 text-xs font-semibold uppercase text-muted-foreground">
                <tr>
                  <th className="p-4">User</th>
                  <th className="p-4">Assigned Spatie Roles</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {users.data.map((u) => {
                  const userRoleNames = u.roles.map((r) => r.name);

                  return (
                    <tr key={u.id} className="hover:bg-muted/30">
                      <td className="p-4">
                        <div className="font-medium text-foreground">{u.name}</div>
                        <div className="text-xs text-muted-foreground">{u.email}</div>
                      </td>
                      <td className="p-4">
                        <div className="flex flex-wrap gap-2">
                          {roles.map((role) => {
                            const isAssigned = userRoleNames.includes(role.name);
                            return (
                              <button
                                key={role.id}
                                type="button"
                                onClick={() => handleUserRoleToggle(u.id, userRoleNames, role.name)}
                                className={`px-2.5 py-1 text-xs rounded font-medium capitalize border transition-all ${
                                  isAssigned
                                    ? 'bg-primary text-primary-foreground border-primary shadow-xs'
                                    : 'bg-background text-muted-foreground border-input hover:border-primary/50'
                                }`}
                              >
                                {isAssigned ? `✓ ${role.name}` : `+ ${role.name}`}
                              </button>
                            );
                          })}
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </Card>

        {/* Modal Create New Role */}
        {showCreateModal && (
          <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <Card className="max-w-lg w-full">
              <CardHeader>
                <CardTitle className="text-lg">Create Custom Role</CardTitle>
              </CardHeader>
              <CardContent>
                <form onSubmit={handleCreateRoleSubmit} className="space-y-4">
                  <div className="space-y-2">
                    <Label htmlFor="role_name">Role Name</Label>
                    <Input
                      id="role_name"
                      placeholder="e.g. instructor, editor, mentor"
                      value={createForm.data.name}
                      onChange={(e) => createForm.setData('name', e.target.value)}
                      required
                    />
                    {createForm.errors.name && (
                      <p className="text-xs text-destructive">{createForm.errors.name}</p>
                    )}
                  </div>

                  <div className="space-y-2">
                    <Label>Assign Initial Permissions</Label>
                    <div className="max-h-60 overflow-y-auto border border-border rounded p-3 space-y-2 text-xs">
                      {permissions.map((p) => {
                        const isChecked = createForm.data.permissions.includes(p.name);
                        return (
                          <label key={p.id} className="flex items-center gap-2 cursor-pointer">
                            <input
                              type="checkbox"
                              checked={isChecked}
                              onChange={() => {
                                if (isChecked) {
                                  createForm.setData(
                                    'permissions',
                                    createForm.data.permissions.filter((perm) => perm !== p.name)
                                  );
                                } else {
                                  createForm.setData('permissions', [...createForm.data.permissions, p.name]);
                                }
                              }}
                              className="rounded border-input text-primary"
                            />
                            <span className="font-mono">{p.name}</span>
                          </label>
                        );
                      })}
                    </div>
                  </div>

                  <div className="flex justify-end gap-2 pt-4 border-t border-border">
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() => setShowCreateModal(false)}
                    >
                      Cancel
                    </Button>
                    <Button type="submit" disabled={createForm.processing}>
                      Create Role
                    </Button>
                  </div>
                </form>
              </CardContent>
            </Card>
          </div>
        )}
      </div>
    </AdminLayout>
  );
}
