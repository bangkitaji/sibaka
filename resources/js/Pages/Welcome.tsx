import AppLayout from '@/Layouts/AppLayout';

export default function Welcome() {
  return (
    <AppLayout title="Home">
      <div className="py-12">
        <div className="text-center">
          <h1 className="text-4xl font-bold tracking-tight text-foreground tablet:text-5xl">
            SIBAKA Portal
          </h1>
          <p className="mt-4 text-lg text-muted-foreground max-w-2xl mx-auto">
            Sinau Bareng Kamisetembang — Knowledge sharing platform for IT
            alumni of STM Pembangunan Semarang.
          </p>
          <div className="mt-8 flex flex-col gap-4 tablet:flex-row tablet:justify-center">
            <a
              href="/register"
              className="min-h-touch inline-flex items-center justify-center rounded-md bg-primary px-6 py-3 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
            >
              Join the Community
            </a>
            <a
              href="/login"
              className="min-h-touch inline-flex items-center justify-center rounded-md border border-input bg-background px-6 py-3 text-sm font-medium text-foreground hover:bg-accent transition-colors"
            >
              Sign In
            </a>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
