import { useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Button } from '@/Components/UI/button';
import { Input } from '@/Components/UI/input';
import { Label } from '@/Components/UI/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/UI/card';
import type { SharedPageProps } from '@/types/index.d';

interface SettingsData {
  app_name: string;
  app_description: string;
  maintenance_mode: boolean;
  registration_enabled: boolean;
  invite_code_required: boolean;
  max_failed_login_attempts: number;
  auto_approve_content: boolean;
  allow_anonymous_posts: boolean;
}

interface SettingsPageProps extends SharedPageProps {
  settings: SettingsData;
}

export default function AdminSettingsIndex() {
  const { settings } = usePage<SettingsPageProps>().props;

  const { data, setData, put, processing, errors } = useForm({
    app_name: settings.app_name,
    app_description: settings.app_description,
    maintenance_mode: settings.maintenance_mode,
    registration_enabled: settings.registration_enabled,
    invite_code_required: settings.invite_code_required,
    max_failed_login_attempts: settings.max_failed_login_attempts,
    auto_approve_content: settings.auto_approve_content,
    allow_anonymous_posts: settings.allow_anonymous_posts,
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put('/admin/settings');
  };

  return (
    <AdminLayout title="Site Settings">
      <div className="max-w-4xl mx-auto space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Application Configuration & Settings</h1>
          <p className="text-sm text-muted-foreground">
            Manage global site parameters, access control, and content publishing policies.
          </p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* General Site Config */}
          <Card>
            <CardHeader>
              <CardTitle className="text-base">General Information</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="app_name">Application Name</Label>
                <Input
                  id="app_name"
                  value={data.app_name}
                  onChange={(e) => setData('app_name', e.target.value)}
                  required
                />
                {errors.app_name && <p className="text-xs text-destructive">{errors.app_name}</p>}
              </div>

              <div className="space-y-2">
                <Label htmlFor="app_description">Application Description</Label>
                <textarea
                  id="app_description"
                  rows={3}
                  value={data.app_description}
                  onChange={(e) => setData('app_description', e.target.value)}
                  className="w-full rounded-md border border-input bg-background p-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                />
                {errors.app_description && <p className="text-xs text-destructive">{errors.app_description}</p>}
              </div>

              <div className="pt-2 border-t border-border">
                <label className="flex items-center gap-3 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={data.maintenance_mode}
                    onChange={(e) => setData('maintenance_mode', e.target.checked)}
                    className="h-4 w-4 rounded border-input text-primary"
                  />
                  <div>
                    <span className="text-sm font-semibold text-foreground block">Maintenance Mode</span>
                    <span className="text-xs text-muted-foreground">
                      When enabled, non-admin users will see a maintenance notice page.
                    </span>
                  </div>
                </label>
              </div>
            </CardContent>
          </Card>

          {/* Authentication & Access Control */}
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Authentication & Access Control</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <label className="flex items-center gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  checked={data.registration_enabled}
                  onChange={(e) => setData('registration_enabled', e.target.checked)}
                  className="h-4 w-4 rounded border-input text-primary"
                />
                <div>
                  <span className="text-sm font-semibold text-foreground block">Allow New User Registrations</span>
                  <span className="text-xs text-muted-foreground">Enable or disable new user sign-ups.</span>
                </div>
              </label>

              <hr className="border-border" />

              <label className="flex items-center gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  checked={data.invite_code_required}
                  onChange={(e) => setData('invite_code_required', e.target.checked)}
                  className="h-4 w-4 rounded border-input text-primary"
                />
                <div>
                  <span className="text-sm font-semibold text-foreground block">Require Invite Code for Registration</span>
                  <span className="text-xs text-muted-foreground">
                    Users must provide a valid invite code during registration.
                  </span>
                </div>
              </label>

              <hr className="border-border" />

              <div className="space-y-2 max-w-xs">
                <Label htmlFor="max_failed_login_attempts">Max Failed Login Attempts</Label>
                <Input
                  id="max_failed_login_attempts"
                  type="number"
                  min={1}
                  max={20}
                  value={data.max_failed_login_attempts}
                  onChange={(e) => setData('max_failed_login_attempts', parseInt(e.target.value) || 5)}
                  required
                />
                <span className="text-xs text-muted-foreground block">
                  Account gets locked temporarily after exceeding this threshold.
                </span>
                {errors.max_failed_login_attempts && (
                  <p className="text-xs text-destructive">{errors.max_failed_login_attempts}</p>
                )}
              </div>
            </CardContent>
          </Card>

          {/* Content & Publishing Policies */}
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Content & Publishing Policies</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <label className="flex items-center gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  checked={data.auto_approve_content}
                  onChange={(e) => setData('auto_approve_content', e.target.checked)}
                  className="h-4 w-4 rounded border-input text-primary"
                />
                <div>
                  <span className="text-sm font-semibold text-foreground block">Auto-Approve Content</span>
                  <span className="text-xs text-muted-foreground">
                    New posts are published immediately without holding for moderation review.
                  </span>
                </div>
              </label>

              <hr className="border-border" />

              <label className="flex items-center gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  checked={data.allow_anonymous_posts}
                  onChange={(e) => setData('allow_anonymous_posts', e.target.checked)}
                  className="h-4 w-4 rounded border-input text-primary"
                />
                <div>
                  <span className="text-sm font-semibold text-foreground block">Allow Anonymous Posts</span>
                  <span className="text-xs text-muted-foreground">
                    Permit members to post content anonymously using tokenized metadata.
                  </span>
                </div>
              </label>
            </CardContent>
          </Card>

          {/* Submit Action */}
          <div className="flex justify-end">
            <Button type="submit" size="lg" disabled={processing}>
              Save Configuration
            </Button>
          </div>
        </form>
      </div>
    </AdminLayout>
  );
}
