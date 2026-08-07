<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\DraftController;
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

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| API v1 routes for the SIBAKA portal. These routes are loaded by the
| application and assigned to the "api" middleware group.
| All routes are prefixed with /api/v1.
|
*/

Route::prefix('v1')->group(function () {
    // ─────────────────────────────────────────────────────────────────────────
    // Public API routes
    // ─────────────────────────────────────────────────────────────────────────

    // Content - public read access
    Route::get('/content', [ContentController::class, 'index'])->name('api.content.index');
    Route::get('/content/{content}', [ContentController::class, 'show'])->name('api.content.show');

    // Comments - public read access
    Route::get('/content/{content}/comments', [CommentController::class, 'index'])->name('api.comments.index');

    // ─────────────────────────────────────────────────────────────────────────
    // Authenticated API routes
    // ─────────────────────────────────────────────────────────────────────────

    Route::middleware(['auth:sanctum'])->group(function () {
        // Tag search
        Route::get('/tags/search', [TagController::class, 'search'])->name('api.tags.search');

        // ─────────────────────────────────────────────────────────────────────
        // Verified member routes
        // ─────────────────────────────────────────────────────────────────────

        Route::middleware(['verified'])->group(function () {
            // Content CRUD
            Route::post('/content', [ContentController::class, 'store'])->name('api.content.store');
            Route::put('/content/{content}', [ContentController::class, 'update'])->name('api.content.update');
            Route::delete('/content/{content}', [ContentController::class, 'destroy'])->name('api.content.destroy');

            // Draft auto-save
            Route::put('/content/{content}/draft', [DraftController::class, 'update'])->name('api.content.draft.update');
            Route::get('/content/{content}/draft', [DraftController::class, 'show'])->name('api.content.draft.show');

            // Comments
            Route::post('/content/{content}/comments', [CommentController::class, 'store'])->name('api.comments.store');
            Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('api.comments.update');
            Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('api.comments.destroy');
            Route::post('/comments/{comment}/accept', [CommentController::class, 'accept'])->name('api.comments.accept');
            Route::delete('/comments/{comment}/accept', [CommentController::class, 'unaccept'])->name('api.comments.unaccept');

            // Reactions
            Route::post('/content/{content}/reactions', [ReactionController::class, 'store'])->name('api.reactions.store');
            Route::delete('/content/{content}/reactions', [ReactionController::class, 'destroy'])->name('api.reactions.destroy');

            // Profile
            Route::get('/profile', [ProfileController::class, 'show'])->name('api.profile.show');
            Route::put('/profile', [ProfileController::class, 'update'])->name('api.profile.update');

            // Directory
            Route::get('/directory', [DirectoryController::class, 'index'])->name('api.directory.index');
            Route::get('/directory/{user}', [DirectoryController::class, 'show'])->name('api.directory.show');

            // Portal messages
            Route::get('/messages', [MessageController::class, 'index'])->name('api.messages.index');
            Route::post('/messages', [MessageController::class, 'store'])->name('api.messages.store');
            Route::patch('/messages/{message}/read', [MessageController::class, 'markAsRead'])->name('api.messages.read');
            Route::patch('/messages/{message}/unread', [MessageController::class, 'markAsUnread'])->name('api.messages.unread');

            // ─────────────────────────────────────────────────────────────────
            // Moderation routes
            // ─────────────────────────────────────────────────────────────────

            // Member: report content
            Route::post('/moderation/reports', [ReportController::class, 'store'])
                ->name('api.moderation.reports.store');

            // Moderator routes
            Route::prefix('moderation')->middleware(['can:moderate'])->group(function () {
                Route::get('/queue', [QueueController::class, 'index'])
                    ->name('api.moderation.queue.index');

                Route::post('/flags/{report}', [QueueController::class, 'review'])
                    ->name('api.moderation.flags.review');

                Route::post('/suspend', [SuspensionController::class, 'store'])
                    ->name('api.moderation.suspend.store');

                Route::post('/warn', [WarningController::class, 'store'])
                    ->name('api.moderation.warn.store');

                Route::get('/dashboard', [DashboardController::class, 'index'])
                    ->name('api.moderation.dashboard.index');
            });
        });
    });
});
