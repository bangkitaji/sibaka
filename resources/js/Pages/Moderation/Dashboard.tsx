import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/UI/card';
import { Button } from '@/Components/UI/button';
import type { SharedPageProps } from '@/types/index.d';

interface DashboardStats {
  total_posts: number;
  active_users: number;
  total_reactions: number;
  total_comments: number;
  pending_reports: number;
  active_suspensions: number;
  warnings_issued: number;
}

interface DashboardPageProps extends SharedPageProps {
  stats: DashboardStats;
}

export default function ModerationDashboard() {
  const { stats } = usePage<DashboardPageProps>().props;
  const [lastRefreshed, setLastRefreshed] = useState<Date>(new Date());

  // Auto-refresh dashboard stats every 60 seconds (Requirement 12.3)
  useEffect(() => {
    const interval = setInterval(() => {
      router.reload({ only: ['stats'] });
      setLastRefreshed(new Date());
    }, 60000);

    return () => clearInterval(interval);
  }, []);

  const formatTime = (date: Date) => {
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  };

  return (
    <AppLayout title="Moderation Dashboard">
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col gap-4 tablet:flex-row tablet:items-center tablet:justify-between">
          <div>
            <h1 className="text-2xl font-bold text-foreground">
              Moderation Dashboard
            </h1>
            <p className="mt-1 text-sm text-muted-foreground">
              Overview of platform activity and moderation metrics.
            </p>
          </div>
          <div className="flex items-center gap-3">
            <span className="text-xs text-muted-foreground">
              Last refreshed: {formatTime(lastRefreshed)}
            </span>
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                router.reload({ only: ['stats'] });
                setLastRefreshed(new Date());
              }}
              aria-label="Refresh dashboard stats"
            >
              Refresh
            </Button>
            <Button
              onClick={() => router.visit('/moderation/queue')}
              aria-label="Go to moderation queue"
            >
              View Queue
            </Button>
          </div>
        </div>

        {/* Content Volume Stats */}
        <section aria-label="Content volume statistics">
          <h2 className="text-lg font-semibold text-foreground mb-3">
            Content Volume
          </h2>
          <div className="grid grid-cols-1 gap-4 tablet:grid-cols-2 desktop:grid-cols-4">
            <StatCard
              title="Total Posts"
              value={stats.total_posts}
              icon={
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                  <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
                  <polyline points="14 2 14 8 20 8" />
                </svg>
              }
            />
            <StatCard
              title="Active Users"
              value={stats.active_users}
              icon={
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                  <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                  <circle cx="9" cy="7" r="4" />
                  <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                  <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>
              }
            />
            <StatCard
              title="Total Reactions"
              value={stats.total_reactions}
              icon={
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                  <path d="M7 10v12" />
                  <path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2h0a3.13 3.13 0 0 1 3 3.88Z" />
                </svg>
              }
            />
            <StatCard
              title="Total Comments"
              value={stats.total_comments}
              icon={
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                  <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                </svg>
              }
            />
          </div>
        </section>

        {/* Moderation Actions Stats */}
        <section aria-label="Moderation action statistics">
          <h2 className="text-lg font-semibold text-foreground mb-3">
            Moderation Actions
          </h2>
          <div className="grid grid-cols-1 gap-4 tablet:grid-cols-3">
            <StatCard
              title="Pending Reports"
              value={stats.pending_reports}
              variant={stats.pending_reports > 0 ? 'warning' : 'default'}
              icon={
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                  <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z" />
                  <line x1="4" x2="4" y1="22" y2="15" />
                </svg>
              }
            />
            <StatCard
              title="Active Suspensions"
              value={stats.active_suspensions}
              variant={stats.active_suspensions > 0 ? 'danger' : 'default'}
              icon={
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                  <circle cx="12" cy="12" r="10" />
                  <line x1="4.93" x2="19.07" y1="4.93" y2="19.07" />
                </svg>
              }
            />
            <StatCard
              title="Warnings Issued"
              value={stats.warnings_issued}
              icon={
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                  <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                  <line x1="12" x2="12" y1="9" y2="13" />
                  <line x1="12" x2="12.01" y1="17" y2="17" />
                </svg>
              }
            />
          </div>
        </section>

        {/* Quick Actions */}
        <section aria-label="Quick actions">
          <h2 className="text-lg font-semibold text-foreground mb-3">
            Quick Actions
          </h2>
          <div className="flex flex-wrap gap-3">
            <Button
              variant="outline"
              onClick={() => router.visit('/moderation/queue')}
              aria-label="Review flagged content"
            >
              Review Flagged Content ({stats.pending_reports})
            </Button>
          </div>
        </section>
      </div>
    </AppLayout>
  );
}

// StatCard component for dashboard metrics
interface StatCardProps {
  title: string;
  value: number;
  icon?: React.ReactNode;
  variant?: 'default' | 'warning' | 'danger';
}

function StatCard({ title, value, icon, variant = 'default' }: StatCardProps) {
  const variantClasses = {
    default: '',
    warning: 'border-orange-200 dark:border-orange-800',
    danger: 'border-red-200 dark:border-red-800',
  };

  const valueClasses = {
    default: 'text-foreground',
    warning: 'text-orange-600 dark:text-orange-400',
    danger: 'text-red-600 dark:text-red-400',
  };

  return (
    <Card className={variantClasses[variant]}>
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle className="text-sm font-medium text-muted-foreground">
          {title}
        </CardTitle>
        {icon && (
          <div className="text-muted-foreground">{icon}</div>
        )}
      </CardHeader>
      <CardContent>
        <p className={`text-2xl font-bold ${valueClasses[variant]}`}>
          {value.toLocaleString()}
        </p>
      </CardContent>
    </Card>
  );
}
