<?php

namespace App\Providers;

use App\Contracts\AnonymityServiceInterface;
use App\Contracts\AuthServiceInterface;
use App\Contracts\CommentServiceInterface;
use App\Contracts\ContentServiceInterface;
use App\Contracts\DirectoryServiceInterface;
use App\Contracts\ModerationServiceInterface;
use App\Contracts\ReactionServiceInterface;
use App\Models\Comment;
use App\Models\Content;
use App\Models\ModerationLog;
use App\Models\User;
use App\Policies\CommentPolicy;
use App\Policies\ContentPolicy;
use App\Policies\ModerationPolicy;
use App\Services\AnonymityService;
use App\Services\AuthService;
use App\Services\CommentService;
use App\Services\ContentService;
use App\Services\DirectoryService;
use App\Services\ModerationService;
use App\Services\ReactionService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AnonymityServiceInterface::class, AnonymityService::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(CommentServiceInterface::class, CommentService::class);
        $this->app->bind(ContentServiceInterface::class, ContentService::class);
        $this->app->bind(DirectoryServiceInterface::class, DirectoryService::class);
        $this->app->bind(ModerationServiceInterface::class, ModerationService::class);
        $this->app->bind(ReactionServiceInterface::class, ReactionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS in production for TLS 1.3 requirement
        if ($this->app->environment('production') || config('app.force_https')) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();
        $this->registerPolicies();
        $this->registerGates();
        $this->registerEventListeners();
    }

    /**
     * Configure the rate limiters for the application.
     *
     * Uses Redis as the backing store (configured via CACHE_DRIVER=redis).
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('ip_rate_limit', function (Request $request) {
            return Limit::perMinute(config('sibaka.rate_limit_per_minute', 100))
                ->by($request->ip());
        });
    }

    /**
     * Register model policies.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(Content::class, ContentPolicy::class);
        Gate::policy(Comment::class, CommentPolicy::class);
        Gate::policy(ModerationLog::class, ModerationPolicy::class);
    }

    /**
     * Register gate definitions for role-based access control.
     */
    protected function registerGates(): void
    {
        // Implicitly grant 'super-admin' role all permissions
        Gate::before(function (User $user, string $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });

        // Moderator or admin access
        Gate::define('moderate', function (User $user): bool {
            return $user->isModerator();
        });

        // Admin-only access
        Gate::define('admin', function (User $user): bool {
            return $user->isAdmin();
        });

        // Admin-only: view moderation logs
        Gate::define('view-moderation-logs', function (User $user): bool {
            return $user->isAdmin();
        });

        // Verified member directory access
        Gate::define('access-directory', function (User $user): bool {
            return $user->isActiveMember();
        });

        // Verified member content creation (not suspended)
        Gate::define('create-content', function (User $user): bool {
            return $user->isActiveMember();
        });
    }

    /**
     * Register event listeners for graceful degradation.
     *
     * Handles email service failures by creating in-app notifications
     * when email delivery persistently fails.
     */
    protected function registerEventListeners(): void
    {
        Event::listen(
            NotificationFailed::class,
            \App\Listeners\HandleFailedEmailNotification::class
        );
    }
}
