import { type PropsWithChildren } from 'react';
import { Head, Link } from '@inertiajs/react';

interface AuthLayoutProps extends PropsWithChildren {
  title?: string;
}

export default function AuthLayout({ title, children }: AuthLayoutProps) {
  return (
    <>
      {title && <Head title={title} />}

      <div className="min-h-screen flex flex-col items-center justify-center bg-background px-4 py-8">
        {/* Brand */}
        <div className="mb-8 text-center">
          <Link href="/" className="inline-block">
            <h1 className="text-3xl font-bold text-primary">SIBAKA</h1>
            <p className="mt-1 text-sm text-muted-foreground">
              Sinau Bareng Kamisetembang
            </p>
          </Link>
        </div>

        {/* Card Container */}
        <div className="w-full max-w-md rounded-lg border border-border bg-card p-6 shadow-sm tablet:p-8">
          {children}
        </div>

        {/* Footer */}
        <p className="mt-6 text-center text-xs text-muted-foreground">
          STM Pembangunan Semarang Alumni IT Portal
        </p>
      </div>
    </>
  );
}
