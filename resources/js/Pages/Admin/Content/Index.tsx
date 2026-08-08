import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Button } from '@/Components/UI/button';
import { Input } from '@/Components/UI/input';
import { Card } from '@/Components/UI/card';
import type { SharedPageProps } from '@/types/index.d';

interface ContentItem {
  id: string;
  title: string;
  category: string;
  status: string;
  is_locked: boolean;
  is_anonymous: boolean;
  deleted_at: string | null;
  created_at: string;
  author?: { name: string; email: string };
}

interface PaginatedContent {
  data: ContentItem[];
  current_page: number;
  last_page: number;
  total: number;
}

interface ContentIndexProps extends SharedPageProps {
  contents: PaginatedContent;
  filters: {
    search?: string;
    category?: string;
    status?: string;
    trashed?: string;
  };
  categories: string[];
  statuses: string[];
}

export default function AdminContentIndex() {
  const { contents, filters, categories, statuses } = usePage<ContentIndexProps>().props;

  const [search, setSearch] = useState(filters.search || '');
  const [selectedCategory, setSelectedCategory] = useState(filters.category || '');
  const [selectedStatus, setSelectedStatus] = useState(filters.status || '');
  const [trashedFilter, setTrashedFilter] = useState(filters.trashed || '');

  const handleFilter = (e: React.FormEvent) => {
    e.preventDefault();
    router.get('/admin/content', {
      search,
      category: selectedCategory,
      status: selectedStatus,
      trashed: trashedFilter,
    }, { preserveState: true });
  };

  const handleToggleLock = (id: string, isLocked: boolean) => {
    const action = isLocked ? 'unlock' : 'lock';
    if (confirm(`Are you sure you want to ${action} this post?`)) {
      router.post(`/admin/content/${id}/toggle-lock`);
    }
  };

  const handleDelete = (id: string) => {
    if (confirm('Are you sure you want to delete this post?')) {
      router.delete(`/admin/content/${id}`);
    }
  };

  const handleRestore = (id: string) => {
    if (confirm('Restore this post?')) {
      router.post(`/admin/content/${id}/restore`);
    }
  };

  return (
    <AdminLayout title="Content Management">
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Content Management</h1>
          <p className="text-sm text-muted-foreground">
            Total {contents.total} posts listed.
          </p>
        </div>

        {/* Filter Bar */}
        <Card className="p-4">
          <form onSubmit={handleFilter} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <Input
              placeholder="Search by title or body..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />

            <select
              value={selectedCategory}
              onChange={(e) => setSelectedCategory(e.target.value)}
              className="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
            >
              <option value="">All Categories</option>
              {categories.map((cat) => (
                <option key={cat} value={cat} className="capitalize">{cat.replace('_', ' ')}</option>
              ))}
            </select>

            <select
              value={selectedStatus}
              onChange={(e) => setSelectedStatus(e.target.value)}
              className="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
            >
              <option value="">All Statuses</option>
              {statuses.map((st) => (
                <option key={st} value={st} className="capitalize">{st}</option>
              ))}
            </select>

            <select
              value={trashedFilter}
              onChange={(e) => setTrashedFilter(e.target.value)}
              className="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
            >
              <option value="">Active Posts</option>
              <option value="only">Deleted Posts Only</option>
            </select>

            <Button type="submit">Filter</Button>
          </form>
        </Card>

        {/* Content Table */}
        <Card className="overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-border bg-muted/50 text-xs font-semibold uppercase text-muted-foreground">
                <tr>
                  <th className="p-4">Title & Author</th>
                  <th className="p-4">Category</th>
                  <th className="p-4">Status</th>
                  <th className="p-4">Lock</th>
                  <th className="p-4">Created</th>
                  <th className="p-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {contents.data.map((item) => (
                  <tr key={item.id} className="hover:bg-muted/30 transition-colors">
                    <td className="p-4">
                      <div className="font-medium text-foreground max-w-xs truncate">{item.title}</div>
                      <div className="text-xs text-muted-foreground">
                        by {item.is_anonymous ? 'Anonymous' : (item.author?.name ?? 'Unknown')}
                      </div>
                    </td>
                    <td className="p-4 text-xs font-medium capitalize">
                      {item.category.replace('_', ' ')}
                    </td>
                    <td className="p-4">
                      <span className="px-2 py-0.5 text-xs rounded bg-primary/10 text-primary font-medium capitalize">
                        {item.status}
                      </span>
                    </td>
                    <td className="p-4">
                      {item.is_locked ? (
                        <span className="px-2 py-0.5 text-xs rounded bg-amber-500/10 text-amber-500 font-medium">Locked</span>
                      ) : (
                        <span className="text-xs text-muted-foreground">Unlocked</span>
                      )}
                    </td>
                    <td className="p-4 text-xs text-muted-foreground">
                      {new Date(item.created_at).toLocaleDateString()}
                    </td>
                    <td className="p-4 text-right space-x-2">
                      <Link
                        href={`/admin/content/${item.id}/edit`}
                        className="px-2.5 py-1 text-xs font-medium rounded border border-border hover:bg-accent inline-block"
                      >
                        Edit
                      </Link>
                      <button
                        type="button"
                        onClick={() => handleToggleLock(item.id, item.is_locked)}
                        className="px-2.5 py-1 text-xs font-medium rounded border border-border hover:bg-accent"
                      >
                        {item.is_locked ? 'Unlock' : 'Lock'}
                      </button>

                      {item.deleted_at ? (
                        <button
                          type="button"
                          onClick={() => handleRestore(item.id)}
                          className="px-2.5 py-1 text-xs font-medium rounded bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20"
                        >
                          Restore
                        </button>
                      ) : (
                        <button
                          type="button"
                          onClick={() => handleDelete(item.id)}
                          className="px-2.5 py-1 text-xs font-medium rounded bg-destructive/10 text-destructive hover:bg-destructive/20"
                        >
                          Delete
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {contents.last_page > 1 && (
            <div className="p-4 border-t border-border flex items-center justify-between">
              <span className="text-xs text-muted-foreground">
                Page {contents.current_page} of {contents.last_page}
              </span>
              <div className="flex gap-2">
                {contents.current_page > 1 && (
                  <Link
                    href={`/admin/content?page=${contents.current_page - 1}`}
                    className="px-3 py-1 text-xs rounded border border-border hover:bg-accent"
                  >
                    Previous
                  </Link>
                )}
                {contents.current_page < contents.last_page && (
                  <Link
                    href={`/admin/content?page=${contents.current_page + 1}`}
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
