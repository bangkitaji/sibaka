import { type FormEventHandler } from 'react';
import { Link, useForm } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Button } from '@/Components/UI/button';
import { Input } from '@/Components/UI/input';
import { Label } from '@/Components/UI/label';

interface LoginPageProps {
  locked?: boolean;
  lockedUntil?: string;
}

export default function Login({ locked, lockedUntil }: LoginPageProps) {
  const { data, setData, post, processing, errors } = useForm({
    email: '',
    password: '',
    remember: false,
  });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    post('/login');
  };

  return (
    <AuthLayout title="Login">
      <div className="space-y-6">
        <div className="text-center">
          <h2 className="text-xl font-semibold text-foreground">
            Welcome Back
          </h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Sign in to your SIBAKA account
          </p>
        </div>

        {locked && (
          <div
            role="alert"
            className="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive"
          >
            <p className="font-medium">Account Locked</p>
            <p className="mt-1">
              Too many failed login attempts. Your account is locked
              {lockedUntil ? ` until ${new Date(lockedUntil).toLocaleString()}` : ' temporarily'}.
              Please try again later.
            </p>
          </div>
        )}

        <form onSubmit={submit} className="space-y-4" noValidate>
          {/* Email */}
          <div className="space-y-2">
            <Label htmlFor="email">Email</Label>
            <Input
              id="email"
              type="email"
              value={data.email}
              onChange={(e) => setData('email', e.target.value)}
              placeholder="alumni@example.com"
              required
              autoComplete="email"
              autoFocus
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
            <Label htmlFor="password">Password</Label>
            <Input
              id="password"
              type="password"
              value={data.password}
              onChange={(e) => setData('password', e.target.value)}
              placeholder="Enter your password"
              required
              autoComplete="current-password"
              aria-invalid={!!errors.password}
              aria-describedby={errors.password ? 'password-error' : undefined}
            />
            {errors.password && (
              <p id="password-error" className="text-sm text-destructive" role="alert">
                {errors.password}
              </p>
            )}
          </div>

          {/* Remember Me */}
          <div className="flex items-center gap-2">
            <input
              id="remember"
              type="checkbox"
              checked={data.remember}
              onChange={(e) => setData('remember', e.target.checked)}
              className="h-4 w-4 rounded border-input text-primary focus:ring-ring"
            />
            <Label htmlFor="remember" className="cursor-pointer text-sm font-normal">
              Remember me
            </Label>
          </div>

          {/* Submit */}
          <Button
            type="submit"
            className="w-full"
            disabled={processing}
            aria-busy={processing}
          >
            {processing ? 'Signing in...' : 'Sign In'}
          </Button>
        </form>

        {/* Register link */}
        <p className="text-center text-sm text-muted-foreground">
          Don&apos;t have an account?{' '}
          <Link
            href="/register"
            className="font-medium text-primary hover:underline"
          >
            Register
          </Link>
        </p>
      </div>
    </AuthLayout>
  );
}
