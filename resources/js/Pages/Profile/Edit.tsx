import { type FormEventHandler, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/UI/button';
import { Input } from '@/Components/UI/input';
import { Label } from '@/Components/UI/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/UI/card';
import { useRetry } from '@/Hooks/useRetry';
import type { Profile, SharedPageProps } from '@/types/index.d';

interface ProfileCompletion {
  percentage: number;
  filled_fields: string[];
  missing_fields: string[];
  required_fields_complete: boolean;
}

interface ProfileEditPageProps {
  profile: Profile | null;
  completion: ProfileCompletion;
}

export default function Edit({ profile, completion }: ProfileEditPageProps) {
  const { errors, flash } = usePage<SharedPageProps>().props;

  const [formData, setFormData] = useState({
    job_title: profile?.job_title ?? '',
    company: profile?.company ?? '',
    years_of_experience: profile?.years_of_experience?.toString() ?? '',
    primary_tech_stack: profile?.primary_tech_stack ?? '',
    secondary_tech_stack: profile?.secondary_tech_stack ?? '',
    mentorship_status: profile?.mentorship_status ?? '',
    hiring_status: profile?.hiring_status ?? '',
    availability: profile?.availability ?? '',
    linkedin_url: profile?.linkedin_url ?? '',
    github_url: profile?.github_url ?? '',
  });

  const { status, error, execute } = useRetry({ maxRetries: 3, baseDelay: 1000 });

  const setField = (field: string, value: string) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const submit: FormEventHandler = async (e) => {
    e.preventDefault();

    await execute(() => {
      return new Promise<void>((resolve, reject) => {
        router.put('/profile', formData, {
          preserveScroll: true,
          onSuccess: () => resolve(),
          onError: () => reject(new Error('Profile save failed')),
        });
      });
    });
  };

  const isProcessing = status === 'loading';

  return (
    <AppLayout title="Edit Profile">
      <div className="space-y-6">
        {/* Profile Completion Banner - Req 2.8: orange bg, white text, progress % */}
        {completion.percentage < 80 && (
          <div
            className="rounded-lg bg-orange-500 px-4 py-3 text-white"
            role="alert"
            aria-live="polite"
          >
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  aria-hidden="true"
                >
                  <circle cx="12" cy="12" r="10" />
                  <line x1="12" y1="8" x2="12" y2="12" />
                  <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
                <span className="font-medium">
                  Your profile is {completion.percentage}% complete.{' '}
                  {!completion.required_fields_complete
                    ? 'Fill in required fields to unlock full visibility.'
                    : 'Complete optional fields to improve discoverability.'}
                </span>
              </div>
              <span className="text-lg font-bold">{completion.percentage}%</span>
            </div>
            {/* Progress bar */}
            <div className="mt-2 h-2 w-full rounded-full bg-white/30">
              <div
                className="h-2 rounded-full bg-white transition-all duration-300"
                style={{ width: `${completion.percentage}%` }}
                role="progressbar"
                aria-valuenow={completion.percentage}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-label={`Profile completion: ${completion.percentage}%`}
              />
            </div>
          </div>
        )}

        {/* Success/Error flash messages */}
        {flash?.success && (
          <div
            className="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200"
            role="alert"
          >
            {flash.success}
          </div>
        )}

        {/* Retry failure message - Req 2.6 */}
        {status === 'failed' && (
          <div
            className="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive"
            role="alert"
          >
            <p className="font-medium">Save Failed</p>
            <p className="mt-1">
              {error || 'Unable to save profile after multiple attempts. Your data has been preserved. Please try again.'}
            </p>
          </div>
        )}

        <form onSubmit={submit} className="space-y-6" noValidate>
          {/* Required Fields */}
          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Required Information</CardTitle>
              <p className="text-sm text-muted-foreground">
                These fields must be completed for your profile to appear in the directory.
              </p>
            </CardHeader>
            <CardContent className="space-y-4">
              {/* Job Title */}
              <div className="space-y-2">
                <Label htmlFor="job_title">
                  Current Job Title <span className="text-destructive">*</span>
                </Label>
                <Input
                  id="job_title"
                  type="text"
                  value={formData.job_title}
                  onChange={(e) => setField('job_title', e.target.value)}
                  placeholder="e.g. Senior Software Engineer"
                  maxLength={100}
                  required
                  aria-invalid={!!errors.job_title}
                  aria-describedby={errors.job_title ? 'job_title-error' : undefined}
                />
                {errors.job_title && (
                  <p id="job_title-error" className="text-sm text-destructive" role="alert">
                    {errors.job_title}
                  </p>
                )}
              </div>

              {/* Company */}
              <div className="space-y-2">
                <Label htmlFor="company">
                  Company <span className="text-destructive">*</span>
                </Label>
                <Input
                  id="company"
                  type="text"
                  value={formData.company}
                  onChange={(e) => setField('company', e.target.value)}
                  placeholder="e.g. Tokopedia"
                  maxLength={100}
                  required
                  aria-invalid={!!errors.company}
                  aria-describedby={errors.company ? 'company-error' : undefined}
                />
                {errors.company && (
                  <p id="company-error" className="text-sm text-destructive" role="alert">
                    {errors.company}
                  </p>
                )}
              </div>

              {/* Years of Experience */}
              <div className="space-y-2">
                <Label htmlFor="years_of_experience">
                  Years of Experience <span className="text-destructive">*</span>
                </Label>
                <Input
                  id="years_of_experience"
                  type="number"
                  min={0}
                  max={50}
                  value={formData.years_of_experience}
                  onChange={(e) => setField('years_of_experience', e.target.value)}
                  placeholder="e.g. 5"
                  required
                  aria-invalid={!!errors.years_of_experience}
                  aria-describedby={
                    errors.years_of_experience ? 'years_of_experience-error' : undefined
                  }
                />
                {errors.years_of_experience && (
                  <p
                    id="years_of_experience-error"
                    className="text-sm text-destructive"
                    role="alert"
                  >
                    {errors.years_of_experience}
                  </p>
                )}
              </div>

              {/* Primary Tech Stack */}
              <div className="space-y-2">
                <Label htmlFor="primary_tech_stack">
                  Primary Tech Stack <span className="text-destructive">*</span>
                </Label>
                <Input
                  id="primary_tech_stack"
                  type="text"
                  value={formData.primary_tech_stack}
                  onChange={(e) => setField('primary_tech_stack', e.target.value)}
                  placeholder="e.g. React, TypeScript, Node.js, PostgreSQL"
                  maxLength={200}
                  required
                  aria-invalid={!!errors.primary_tech_stack}
                  aria-describedby={
                    errors.primary_tech_stack ? 'primary_tech_stack-error' : undefined
                  }
                />
                {errors.primary_tech_stack && (
                  <p
                    id="primary_tech_stack-error"
                    className="text-sm text-destructive"
                    role="alert"
                  >
                    {errors.primary_tech_stack}
                  </p>
                )}
              </div>
            </CardContent>
          </Card>

          {/* Optional Fields */}
          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Optional Information</CardTitle>
              <p className="text-sm text-muted-foreground">
                These fields help others find and connect with you more easily.
              </p>
            </CardHeader>
            <CardContent className="space-y-4">
              {/* Secondary Tech Stack */}
              <div className="space-y-2">
                <Label htmlFor="secondary_tech_stack">Secondary Tech Stack</Label>
                <Input
                  id="secondary_tech_stack"
                  type="text"
                  value={formData.secondary_tech_stack}
                  onChange={(e) => setField('secondary_tech_stack', e.target.value)}
                  placeholder="e.g. Python, Docker, AWS"
                  maxLength={200}
                  aria-invalid={!!errors.secondary_tech_stack}
                  aria-describedby={
                    errors.secondary_tech_stack ? 'secondary_tech_stack-error' : undefined
                  }
                />
                {errors.secondary_tech_stack && (
                  <p
                    id="secondary_tech_stack-error"
                    className="text-sm text-destructive"
                    role="alert"
                  >
                    {errors.secondary_tech_stack}
                  </p>
                )}
              </div>

              {/* Mentorship Status */}
              <div className="space-y-2">
                <Label htmlFor="mentorship_status">Mentorship</Label>
                <select
                  id="mentorship_status"
                  value={formData.mentorship_status}
                  onChange={(e) => setField('mentorship_status', e.target.value)}
                  className="flex h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 min-h-touch"
                  aria-invalid={!!errors.mentorship_status}
                  aria-describedby={
                    errors.mentorship_status ? 'mentorship_status-error' : undefined
                  }
                >
                  <option value="">Select mentorship status</option>
                  <option value="willing">Willing to mentor</option>
                  <option value="not_willing">Not available</option>
                </select>
                {errors.mentorship_status && (
                  <p
                    id="mentorship_status-error"
                    className="text-sm text-destructive"
                    role="alert"
                  >
                    {errors.mentorship_status}
                  </p>
                )}
              </div>

              {/* Hiring Status */}
              <div className="space-y-2">
                <Label htmlFor="hiring_status">Hiring Status</Label>
                <select
                  id="hiring_status"
                  value={formData.hiring_status}
                  onChange={(e) => setField('hiring_status', e.target.value)}
                  className="flex h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 min-h-touch"
                  aria-invalid={!!errors.hiring_status}
                  aria-describedby={
                    errors.hiring_status ? 'hiring_status-error' : undefined
                  }
                >
                  <option value="">Select hiring status</option>
                  <option value="open_to_hiring">Open to hiring</option>
                  <option value="seeking_job">Seeking job</option>
                  <option value="internship">Looking for internship</option>
                  <option value="none">Not applicable</option>
                </select>
                {errors.hiring_status && (
                  <p
                    id="hiring_status-error"
                    className="text-sm text-destructive"
                    role="alert"
                  >
                    {errors.hiring_status}
                  </p>
                )}
              </div>

              {/* Availability */}
              <div className="space-y-2">
                <Label htmlFor="availability">Availability</Label>
                <select
                  id="availability"
                  value={formData.availability}
                  onChange={(e) => setField('availability', e.target.value)}
                  className="flex h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 min-h-touch"
                  aria-invalid={!!errors.availability}
                  aria-describedby={
                    errors.availability ? 'availability-error' : undefined
                  }
                >
                  <option value="">Select availability</option>
                  <option value="immediate">Immediate</option>
                  <option value="1_month">Within 1 month</option>
                  <option value="2_months">Within 2 months</option>
                  <option value="3_months_plus">3+ months</option>
                </select>
                {errors.availability && (
                  <p
                    id="availability-error"
                    className="text-sm text-destructive"
                    role="alert"
                  >
                    {errors.availability}
                  </p>
                )}
              </div>

              {/* LinkedIn URL */}
              <div className="space-y-2">
                <Label htmlFor="linkedin_url">LinkedIn Profile URL</Label>
                <Input
                  id="linkedin_url"
                  type="url"
                  value={formData.linkedin_url}
                  onChange={(e) => setField('linkedin_url', e.target.value)}
                  placeholder="https://linkedin.com/in/yourname"
                  maxLength={200}
                  aria-invalid={!!errors.linkedin_url}
                  aria-describedby={
                    errors.linkedin_url ? 'linkedin_url-error' : undefined
                  }
                />
                {errors.linkedin_url && (
                  <p
                    id="linkedin_url-error"
                    className="text-sm text-destructive"
                    role="alert"
                  >
                    {errors.linkedin_url}
                  </p>
                )}
              </div>

              {/* GitHub URL */}
              <div className="space-y-2">
                <Label htmlFor="github_url">GitHub Profile URL</Label>
                <Input
                  id="github_url"
                  type="url"
                  value={formData.github_url}
                  onChange={(e) => setField('github_url', e.target.value)}
                  placeholder="https://github.com/yourname"
                  maxLength={200}
                  aria-invalid={!!errors.github_url}
                  aria-describedby={
                    errors.github_url ? 'github_url-error' : undefined
                  }
                />
                {errors.github_url && (
                  <p
                    id="github_url-error"
                    className="text-sm text-destructive"
                    role="alert"
                  >
                    {errors.github_url}
                  </p>
                )}
              </div>
            </CardContent>
          </Card>

          {/* Submit Button */}
          <div className="flex justify-end">
            <Button
              type="submit"
              disabled={isProcessing}
              aria-busy={isProcessing}
            >
              {isProcessing ? 'Saving...' : 'Save Profile'}
            </Button>
          </div>
        </form>
      </div>
    </AppLayout>
  );
}
