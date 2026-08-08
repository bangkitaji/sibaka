import { type PropsWithChildren } from 'react';
import { Head, Link } from '@inertiajs/react';

interface AuthLayoutProps extends PropsWithChildren {
  title?: string;
}

export default function AuthLayout({ title, children }: AuthLayoutProps) {
  return (
    <>
      {title && <Head title={title} />}

      <div className="min-h-screen flex bg-background">
        {/* Left Side - Image Background */}
        <div className="hidden desktop:flex desktop:w-1/2 relative bg-primary overflow-hidden">
          <div
            className="absolute inset-0 bg-cover bg-center transition-transform duration-[20s] hover:scale-105"
            style={{ backgroundImage: "url('/images/auth-bg.png')" }}
          />
          <div className="absolute inset-0 bg-primary/20 backdrop-blur-[2px]" />
          <div className="relative z-10 w-full flex flex-col items-center justify-end text-white p-12 h-full bg-gradient-to-t from-primary/80 to-transparent">
             {/* Text over background could go here */}
          </div>
        </div>

        {/* Right Side - Form */}
        <div className="w-full desktop:w-1/2 flex flex-col justify-center px-4 py-8 tablet:px-8 desktop:px-24">
          <div className="mx-auto w-full max-w-md">
            {/* Brand / Logo */}
            <div className="mb-8 text-center">
              <Link href="/" className="inline-flex flex-col items-center group">
                <img
                  src="/images/logo.png"
                  alt="SIBAKA Logo"
                  className="h-32 w-auto mb-4 drop-shadow-sm transition-transform group-hover:scale-105 duration-300"
                />
              </Link>
            </div>

            {/* Card Container */}
            <div className="rounded-xl border border-border/60 bg-card/80 p-6 shadow-sm backdrop-blur-sm tablet:p-8">
              {children}
            </div>

            {/* Footer */}
            <p className="mt-8 text-center text-xs text-muted-foreground">
              STM Pembangunan Semarang Alumni IT Portal
            </p>
          </div>
        </div>
      </div>
    </>
  );
}
