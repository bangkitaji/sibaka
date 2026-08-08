import { Link, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Button } from '@/Components/UI/button';
import { Input } from '@/Components/UI/input';
import { Label } from '@/Components/UI/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/UI/card';
import type { SharedPageProps } from '@/types/index.d';

interface ContentData {
  id: string;
  title: string;
  body: string;
  category: string;
  status: string;
  is_locked: boolean;
  is_anonymous: boolean;
  is_qna: boolean;
  author?: { name: string };
}

interface ContentEditProps extends SharedPageProps {
  content: ContentData;
  categories: string[];
  statuses: string[];
}

export default function AdminContentEdit() {
  const { content, categories, statuses } = usePage<ContentEditProps>().props;

  const { data, setData, put, processing, errors } = useForm({
    title: content.title,
    body: content.body,
    category: content.category,
    status: content.status,
    is_locked: content.is_locked,
    is_anonymous: content.is_anonymous,
    is_qna: content.is_qna,
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(`/admin/content/${content.id}`);
  };

  return (
    <AdminLayout title={`Edit Content - ${content.title}`}>
      <div className="max-w-4xl mx-auto space-y-6">
        <div>
          <Link href="/admin/content" className="text-xs text-primary hover:underline flex items-center gap-1 mb-2">
            &larr; Back to Content Management
          </Link>
          <h1 className="text-2xl font-bold text-foreground">Edit Content (Admin Mode)</h1>
          <p className="text-sm text-muted-foreground">
            Author: {content.author?.name ?? 'Anonymous / Unknown'}
          </p>
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Content Editor</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-6">
              {/* Title */}
              <div className="space-y-2">
                <Label htmlFor="title">Title</Label>
                <Input
                  id="title"
                  value={data.title}
                  onChange={(e) => setData('title', e.target.value)}
                  required
                />
                {errors.title && <p className="text-xs text-destructive">{errors.title}</p>}
              </div>

              {/* Category & Status */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="category">Category</Label>
                  <select
                    id="category"
                    value={data.category}
                    onChange={(e) => setData('category', e.target.value)}
                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring capitalize"
                  >
                    {categories.map((cat) => (
                      <option key={cat} value={cat} className="capitalize">
                        {cat.replace('_', ' ')}
                      </option>
                    ))}
                  </select>
                  {errors.category && <p className="text-xs text-destructive">{errors.category}</p>}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="status">Publication Status</Label>
                  <select
                    id="status"
                    value={data.status}
                    onChange={(e) => setData('status', e.target.value)}
                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring capitalize"
                  >
                    {statuses.map((st) => (
                      <option key={st} value={st} className="capitalize">
                        {st}
                      </option>
                    ))}
                  </select>
                  {errors.status && <p className="text-xs text-destructive">{errors.status}</p>}
                </div>
              </div>

              {/* Body */}
              <div className="space-y-2">
                <Label htmlFor="body">Body (Markdown / Plain Text)</Label>
                <textarea
                  id="body"
                  rows={12}
                  value={data.body}
                  onChange={(e) => setData('body', e.target.value)}
                  className="w-full rounded-md border border-input bg-background p-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                  required
                />
                {errors.body && <p className="text-xs text-destructive">{errors.body}</p>}
              </div>

              {/* Toggles */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-border">
                <label className="flex items-center gap-2 cursor-pointer text-sm">
                  <input
                    type="checkbox"
                    checked={data.is_locked}
                    onChange={(e) => setData('is_locked', e.target.checked)}
                    className="rounded border-input text-primary"
                  />
                  <span>Lock Comments</span>
                </label>

                <label className="flex items-center gap-2 cursor-pointer text-sm">
                  <input
                    type="checkbox"
                    checked={data.is_anonymous}
                    onChange={(e) => setData('is_anonymous', e.target.checked)}
                    className="rounded border-input text-primary"
                  />
                  <span>Anonymous Post</span>
                </label>

                <label className="flex items-center gap-2 cursor-pointer text-sm">
                  <input
                    type="checkbox"
                    checked={data.is_qna}
                    onChange={(e) => setData('is_qna', e.target.checked)}
                    className="rounded border-input text-primary"
                  />
                  <span>Q&A Mode</span>
                </label>
              </div>

              {/* Submit */}
              <div className="flex justify-end gap-3 pt-4 border-t border-border">
                <Link href="/admin/content" className="px-4 py-2 text-sm rounded border border-border hover:bg-accent">
                  Cancel
                </Link>
                <Button type="submit" disabled={processing}>
                  Save Changes
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </AdminLayout>
  );
}
