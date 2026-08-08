import { type FormEventHandler, useState } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Button } from '@/Components/UI/button';
import { Input } from '@/Components/UI/input';
import { Label } from '@/Components/UI/label';
import SearchableYearSelect from '@/Components/UI/SearchableYearSelect';
import SearchableSelect, { type SelectOption } from '@/Components/UI/SearchableSelect';
import type { SharedPageProps } from '@/types/index.d';

interface DepartmentOption {
  id: string;
  code: string;
  name: string;
}

interface RegisterPageProps extends SharedPageProps {
  departments?: DepartmentOption[];
}

export default function Register() {
  const { departments = [] } = usePage<RegisterPageProps>().props;
  const [showOptional, setShowOptional] = useState(false);

  const departmentOptions: SelectOption[] = departments.map((dept) => ({
    value: dept.name,
    label: dept.name,
    sublabel: dept.code,
  }));

  const { data, setData, post, processing, errors } = useForm({
    name: '',
    email: '',
    password: '',
    entry_year: '',
    graduation_year: '',
    department: '',
    linkedin_url: '',
    github_url: '',
    invite_code: '',
  });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    post('/register');
  };

  return (
    <AuthLayout title="Register">
      <div className="space-y-6">
        <div className="text-center">
          <h2 className="text-xl font-semibold text-foreground">
            Create Account
          </h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Join the SIBAKA alumni community
          </p>
        </div>

        <form onSubmit={submit} className="space-y-4" noValidate>
          {/* Required Fields */}
          <fieldset className="space-y-4">
            <legend className="sr-only">Required Information</legend>

            {/* Name */}
            <div className="space-y-2">
              <Label htmlFor="name">
                Full Name <span className="text-destructive">*</span>
              </Label>
              <Input
                id="name"
                type="text"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                placeholder="Your full name"
                required
                maxLength={100}
                autoComplete="name"
                autoFocus
                aria-invalid={!!errors.name}
                aria-describedby={errors.name ? 'name-error' : undefined}
              />
              {errors.name && (
                <p id="name-error" className="text-sm text-destructive" role="alert">
                  {errors.name}
                </p>
              )}
            </div>

            {/* Email */}
            <div className="space-y-2">
              <Label htmlFor="email">
                Email <span className="text-destructive">*</span>
              </Label>
              <Input
                id="email"
                type="email"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                placeholder="alumni@example.com"
                required
                autoComplete="email"
                aria-invalid={!!errors.email}
                aria-describedby={errors.email ? 'email-error' : undefined}
              />
              {errors.email && (
                <p id="email-error" className="text-sm text-destructive" role="alert">
                  {errors.email}
                </p>
              )}
            </div>

            {/* Password */}
            <div className="space-y-2">
              <Label htmlFor="password">
                Password <span className="text-destructive">*</span>
              </Label>
              <Input
                id="password"
                type="password"
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                placeholder="Minimum 8 characters"
                required
                minLength={8}
                autoComplete="new-password"
                aria-invalid={!!errors.password}
                aria-describedby={errors.password ? 'password-error' : undefined}
              />
              {errors.password && (
                <p id="password-error" className="text-sm text-destructive" role="alert">
                  {errors.password}
                </p>
              )}
            </div>

            {/* Academic Info: Year of Entry, Year of Graduation & Department */}
            <div className="space-y-4">
              <div className="grid grid-cols-1 gap-4 tablet:grid-cols-2">
                {/* Year of Entry (Mandatory) */}
                <SearchableYearSelect
                  id="entry_year"
                  label="Year of Entry"
                  value={data.entry_year}
                  onChange={(val) => setData('entry_year', val)}
                  required
                  minYear={1975}
                  error={errors.entry_year}
                  placeholder="Search or select entry year..."
                />

                {/* Year of Graduation (Mandatory) */}
                <SearchableYearSelect
                  id="graduation_year"
                  label="Year of Graduation"
                  value={data.graduation_year}
                  onChange={(val) => setData('graduation_year', val)}
                  required
                  minYear={1979}
                  error={errors.graduation_year}
                  placeholder="Search or select graduation year..."
                />
              </div>

              {/* Department (Searchable) */}
              <SearchableSelect
                id="department"
                label="Department / Jurusan"
                value={data.department}
                options={departmentOptions}
                onChange={(val) => setData('department', val)}
                required
                error={errors.department}
                placeholder="Search by code or department name (e.g. SIJA, TME, TEI)..."
              />
            </div>
          </fieldset>

          {/* Optional Fields Toggle */}
          <div className="border-t border-border pt-4">
            <button
              type="button"
              onClick={() => setShowOptional(!showOptional)}
              className="flex w-full items-center justify-between text-sm font-medium text-muted-foreground hover:text-foreground transition-colors min-h-touch"
              aria-expanded={showOptional}
              aria-controls="optional-fields"
            >
              <span>Optional Information</span>
              <svg
                className={`h-4 w-4 transition-transform ${showOptional ? 'rotate-180' : ''}`}
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
              >
                <path
                  fillRule="evenodd"
                  d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                  clipRule="evenodd"
                />
              </svg>
            </button>

            {showOptional && (
              <fieldset id="optional-fields" className="mt-4 space-y-4">
                <legend className="sr-only">Optional Information</legend>

                {/* LinkedIn URL */}
                <div className="space-y-2">
                  <Label htmlFor="linkedin_url">LinkedIn Profile</Label>
                  <Input
                    id="linkedin_url"
                    type="url"
                    value={data.linkedin_url}
                    onChange={(e) => setData('linkedin_url', e.target.value)}
                    placeholder="https://linkedin.com/in/yourprofile"
                    maxLength={200}
                    autoComplete="url"
                    aria-invalid={!!errors.linkedin_url}
                    aria-describedby={errors.linkedin_url ? 'linkedin-error' : undefined}
                  />
                  {errors.linkedin_url && (
                    <p id="linkedin-error" className="text-sm text-destructive" role="alert">
                      {errors.linkedin_url}
                    </p>
                  )}
                </div>

                {/* GitHub URL */}
                <div className="space-y-2">
                  <Label htmlFor="github_url">GitHub Profile</Label>
                  <Input
                    id="github_url"
                    type="url"
                    value={data.github_url}
                    onChange={(e) => setData('github_url', e.target.value)}
                    placeholder="https://github.com/yourusername"
                    maxLength={200}
                    autoComplete="url"
                    aria-invalid={!!errors.github_url}
                    aria-describedby={errors.github_url ? 'github-error' : undefined}
                  />
                  {errors.github_url && (
                    <p id="github-error" className="text-sm text-destructive" role="alert">
                      {errors.github_url}
                    </p>
                  )}
                </div>

                {/* Invite Code */}
                <div className="space-y-2">
                  <Label htmlFor="invite_code">Invite Code</Label>
                  <Input
                    id="invite_code"
                    type="text"
                    value={data.invite_code}
                    onChange={(e) => setData('invite_code', e.target.value)}
                    placeholder="Enter invite code"
                    aria-invalid={!!errors.invite_code}
                    aria-describedby="invite-code-hint invite-code-error"
                  />
                  <p id="invite-code-hint" className="text-xs text-muted-foreground">
                    Have an invite code? Enter it for instant verification
                  </p>
                  {errors.invite_code && (
                    <p id="invite-code-error" className="text-sm text-destructive" role="alert">
                      {errors.invite_code}
                    </p>
                  )}
                </div>
              </fieldset>
            )}
          </div>

          {/* Submit */}
          <Button
            type="submit"
            className="w-full"
            disabled={processing}
            aria-busy={processing}
          >
            {processing ? 'Creating account...' : 'Create Account'}
          </Button>
        </form>

        {/* Login link */}
        <p className="text-center text-sm text-muted-foreground">
          Already have an account?{' '}
          <Link
            href="/login"
            className="font-medium text-primary hover:underline"
          >
            Sign In
          </Link>
        </p>
      </div>
    </AuthLayout>
  );
}
