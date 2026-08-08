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
        <div className="hidden desktop:flex desktop:w-[60%] relative bg-primary overflow-hidden">
          <div 
            className="absolute inset-0 bg-cover bg-center transition-transform duration-[20s] hover:scale-105"
            style={{ backgroundImage: "url('/images/smkn7_generated_bg.png')" }}
          />
        </div>

        {/* Right Side - Form */}
        <div className="w-full desktop:w-[40%] flex flex-col justify-center px-4 py-8 tablet:px-8 desktop:px-16">
          <div className="mx-auto w-full max-w-md">
            {/* Brand / Logo */}
            <div className="mb-6 text-center">
              <Link href="/" className="inline-flex flex-col items-center group">
                <img 
                  src="/images/logo.png" 
                  alt="SIBAKA Logo" 
                  className="h-24 w-auto drop-shadow-sm transition-transform group-hover:scale-105 duration-300" 
                />
              </Link>
            </div>

            {/* Card Container */}
            <div className="rounded-xl border border-border/60 bg-card/80 p-6 shadow-sm backdrop-blur-sm tablet:p-8">
              {children}
            </div>

            {/* Footer */}
            <div className="mt-8 flex flex-col items-center justify-center gap-2 text-xs text-muted-foreground">
              <span>Empowered and Supported By</span>
              <div className="flex items-center gap-3">
                <img 
                  src="/images/Logo SMKN 7 Semarang.png" 
                  alt="SMK 7 Semarang" 
                  className="h-6 w-auto object-contain"
                />
                <img 
                  src="/images/logo_kamisetembang_hd.png" 
                  alt="Kamisetembang" 
                  className="h-6 w-auto object-contain"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
