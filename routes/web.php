<?php

use App\Http\Controllers\Auth\InviteCodeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\Moderation\DashboardController;
use App\Http\Controllers\Moderation\QueueController;
use App\Http\Controllers\Moderation\ReportController;
use App\Http\Controllers\Moderation\SuspensionController;
use App\Http\Controllers\Moderation\WarningController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReactionController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Inertia page routes and form action routes for the SIBAKA portal.
| Public routes are accessible without authentication.
| Auth routes require login. Verified routes require member verification.
| Moderation routes require the 'can:moderate' gate.
|
*/

// ─────────────────────────────────────────────────────────────────────────────
// Public Routes
// ─────────────────────────────────────────────────────────────────────────────

// System health check - reports infrastructure degradation status
Route::get('/health', HealthCheckController::class)->name('health');

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Content routes - public (index and show)
Route::get('/content', [ContentController::class, 'index'])->name('content.index');

// Content create route must be defined BEFORE the {content} wildcard
Route::get('/content/create', [ContentController::class, 'create'])
    ->middleware(['auth', 'verified'])
    ->name('content.create');

Route::get('/content/{content}', [ContentController::class, 'show'])->name('content.show');

// Comments - public read
Route::get('/content/{content}/comments', [CommentController::class, 'index'])->name('comments.index');

// ─────────────────────────────────────────────────────────────────────────────
// Guest-Only Routes (redirect if authenticated)
// ─────────────────────────────────────────────────────────────────────────────

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);

    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

// ─────────────────────────────────────────────────────────────────────────────
// Authenticated Routes
// ─────────────────────────────────────────────────────────────────────────────

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Tag search API (available to all authenticated users)
    Route::get('/api/tags/search', [TagController::class, 'search'])->name('tags.search');

    // Verification routes (for pending members)
    Route::post('/verify-invite', [VerificationController::class, 'verifyInvite'])->name('verify.invite');
    Route::get('/verification/pending', [VerificationController::class, 'showPending'])->name('verification.pending');

    // ─────────────────────────────────────────────────────────────────────────
    // Authenticated + Verified Routes
    // ─────────────────────────────────────────────────────────────────────────

    Route::middleware('verified')->group(function () {
        // Invite code generation (members can generate invite codes)
        Route::post('/invite-codes', [InviteCodeController::class, 'store'])->name('invite-codes.store');

        // Content creation and management
        Route::post('/content', [ContentController::class, 'store'])->name('content.store');
        Route::get('/content/{content}/edit', [ContentController::class, 'edit'])->name('content.edit');
        Route::put('/content/{content}', [ContentController::class, 'update'])->name('content.update');
        Route::delete('/content/{content}', [ContentController::class, 'destroy'])->name('content.destroy');

        // Draft auto-save routes
        Route::put('/content/{content}/draft', [DraftController::class, 'update'])->name('content.draft.update');
        Route::get('/content/{content}/draft', [DraftController::class, 'show'])->name('content.draft.show');

        // Comment routes
        Route::post('/content/{content}/comments', [CommentController::class, 'store'])->name('comments.store');
        Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
        Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
        Route::post('/comments/{comment}/accept', [CommentController::class, 'accept'])->name('comments.accept');
        Route::delete('/comments/{comment}/accept', [CommentController::class, 'unaccept'])->name('comments.unaccept');

        // Reaction routes
        Route::post('/content/{content}/reactions', [ReactionController::class, 'store'])->name('reactions.store');
        Route::delete('/content/{content}/reactions', [ReactionController::class, 'destroy'])->name('reactions.destroy');

        // Profile routes
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

        // Directory routes
        Route::get('/directory', [DirectoryController::class, 'index'])->name('directory.index');
        Route::get('/directory/{user}', [DirectoryController::class, 'show'])->name('directory.show');

        // Portal messaging routes
        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
        Route::patch('/messages/{message}/read', [MessageController::class, 'markAsRead'])->name('messages.read');
        Route::patch('/messages/{message}/unread', [MessageController::class, 'markAsUnread'])->name('messages.unread');

        // Content reporting (members can report content)
        Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');

        // ─────────────────────────────────────────────────────────────────────
        // Moderation Routes (auth + verified + can:moderate)
        // ─────────────────────────────────────────────────────────────────────

        Route::prefix('moderation')->middleware('can:moderate')->group(function () {
            // Inertia page routes for moderation
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('moderation.dashboard');
            Route::get('/queue', [QueueController::class, 'index'])->name('moderation.queue');

            // Moderation action routes
            Route::post('/flags/{report}', [QueueController::class, 'review'])->name('moderation.flags.review');
            Route::post('/suspend', [SuspensionController::class, 'store'])->name('moderation.suspend');
            Route::post('/warn', [WarningController::class, 'store'])->name('moderation.warn');
        });

        // Admin verification management routes
        Route::prefix('admin')->group(function () {
            Route::post('/verify/{userId}/approve', [VerificationController::class, 'approve'])->name('admin.verify.approve');
            Route::post('/verify/{userId}/reject', [VerificationController::class, 'reject'])->name('admin.verify.reject');
        });
    });
});
