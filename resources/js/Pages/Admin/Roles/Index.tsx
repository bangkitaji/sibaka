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

interface PermissionGroup {
  name: string;
  badge: string;
  permissions: PermissionItem[];
}

function groupPermissions(permissions: PermissionItem[]): PermissionGroup[] {
  const groups: Record<string, { badge: string; items: PermissionItem[] }> = {
    'Admin & Access Management': { badge: '🛡️', items: [] },
    'Learning Center & Courses': { badge: '🎓', items: [] },
    'Content & Moderation': { badge: '📝', items: [] },
    'Community & Directory': { badge: '👥', items: [] },
    'General & Others': { badge: '⚙️', items: [] },
  };

  permissions.forEach((perm) => {
    const name = perm.name.toLowerCase();
    if (
      name.includes('admin') ||
      name.includes('manage-') ||
      name.includes('role') ||
      name.includes('setting') ||
      name.includes('invite') ||
      name.includes('department')
    ) {
      groups['Admin & Access Management'].items.push(perm);
    } else if (
      name.includes('course') ||
      name.includes('workshop') ||
      name.includes('enroll') ||
      name.includes('instructor') ||
      name.includes('learning')
    ) {
      groups['Learning Center & Courses'].items.push(perm);
    } else if (
      name.includes('post') ||
      name.includes('content') ||
      name.includes('comment') ||
      name.includes('moderate') ||
      name.includes('article')
    ) {
      groups['Content & Moderation'].items.push(perm);
    } else if (
      name.includes('directory') ||
      name.includes('profile') ||
      name.includes('alumni') ||
      name.includes('verify')
    ) {
      groups['Community & Directory'].items.push(perm);
    } else {
      groups['General & Others'].items.push(perm);
    }
  });

  return Object.entries(groups)
    .filter(([_, group]) => group.items.length > 0)
    .map(([groupName, group]) => ({
      name: groupName,
      badge: group.badge,
      permissions: group.items,
    }));
}

export default function AdminRolesIndex() {
  const { roles, permissions, users, filters } = usePage<RolesIndexProps>().props;

  const [selectedRole, setSelectedRole] = useState<RoleItem>(roles[0] || null);
  const [rolePermissions, setRolePermissions] = useState<string[]>(
    roles[0]?.permissions.map((p) => p.name) || []
  );
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [searchUser, setSearchUser] = useState(filters.search || '');

  const groupedPermissionsList = groupPermissions(permissions);

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

  const handleGroupSelectAll = (groupPerms: PermissionItem[]) => {
    const names = groupPerms.map((p) => p.name);
    const updated = Array.from(new Set([...rolePermissions, ...names]));
    setRolePermissions(updated);
  };

  const handleGroupDeselectAll = (groupPerms: PermissionItem[]) => {
    const names = groupPerms.map((p) => p.name);
    const updated = rolePermissions.filter((p) => !names.includes(p));
    setRolePermissions(updated);
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
    <AdminLayout title="Role Management">
      <div className="space-y-8">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold text-foreground">Role Management (RBAC)</h1>
            <p className="text-sm text-muted-foreground">
              Configure dynamic role permissions grouped by functional domains and assign roles to users.
            </p>
          </div>
          <Button onClick={() => setShowCreateModal(true)}>
            + Create Custom Role
          </Button>
        </div>

        {/* Section 1: Role Permissions Matrix (Grouped) */}
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

          {/* Grouped Permissions Matrix for Selected Role */}
          <Card className="lg:col-span-3">
            <CardHeader className="flex flex-row items-center justify-between">
              <div>
                <CardTitle className="text-lg capitalize">
                  Permissions for Role: <span className="text-primary">{selectedRole?.name}</span>
                </CardTitle>
                <p className="text-xs text-muted-foreground mt-1">
                  Permissions are grouped by feature categories for easier configuration.
                </p>
              </div>
              <Button onClick={handleSaveRolePermissions} size="sm">
                Save Matrix Changes
              </Button>
            </CardHeader>
            <CardContent className="space-y-6">
              {groupedPermissionsList.map((group) => {
                const isSuperAdmin = selectedRole?.name === 'super-admin';

                return (
                  <div key={group.name} className="space-y-3 border-b border-border/50 pb-6 last:border-0 last:pb-0">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <span className="text-base">{group.badge}</span>
                        <h3 className="text-sm font-bold text-foreground">
                          {group.name}
                        </h3>
                        <span className="text-xs text-muted-foreground bg-muted px-2 py-0.5 rounded-full font-mono">
                          {group.permissions.length}
                        </span>
                      </div>

                      {!isSuperAdmin && (
                        <div className="flex items-center gap-2 text-xs">
                          <button
                            type="button"
                            onClick={() => handleGroupSelectAll(group.permissions)}
                            className="text-primary hover:underline font-medium"
                          >
                            Select All
                          </button>
                          <span className="text-muted-foreground">&middot;</span>
                          <button
                            type="button"
                            onClick={() => handleGroupDeselectAll(group.permissions)}
                            className="text-muted-foreground hover:text-foreground hover:underline"
                          >
                            Deselect All
                          </button>
                        </div>
                      )}
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                      {group.permissions.map((perm) => {
                        const isChecked = rolePermissions.includes(perm.name);

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
                  </div>
                );
              })}
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
            <Card className="max-w-xl w-full">
              <CardHeader>
                <CardTitle className="text-lg">Create Custom Role</CardTitle>
              </CardHeader>
              <CardContent>
                <form onSubmit={handleCreateRoleSubmit} className="space-y-4">
                  <div className="space-y-2">
                    <Label htmlFor="role_name">Role Name *</Label>
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

                  <div className="space-y-3">
                    <Label>Assign Initial Permissions (Grouped)</Label>
                    <div className="max-h-72 overflow-y-auto border border-border rounded-lg p-3 space-y-4 text-xs">
                      {groupedPermissionsList.map((group) => (
                        <div key={group.name} className="space-y-2">
                          <div className="font-bold text-foreground flex items-center gap-1.5 border-b border-border/40 pb-1">
                            <span>{group.badge}</span>
                            <span>{group.name}</span>
                          </div>
                          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 pl-2">
                            {group.permissions.map((p) => {
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
                                  <span className="font-mono text-xs">{p.name}</span>
                                </label>
                              );
                            })}
                          </div>
                        </div>
                      ))}
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
