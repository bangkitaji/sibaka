<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Content;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class AutoLockThreads implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     *
     * Lock all threads (content) that have had no activity for 90 days.
     * Activity is defined as either the last comment's created_at timestamp,
     * or the content's created_at if there are no comments.
     */
    public function handle(): void
    {
        $threshold = Carbon::now()->subDays(90);

        // Find unlocked content where last activity is older than 90 days.
        // Last activity = latest comment created_at, or content created_at if no comments.
        $count = Content::query()
            ->where('is_locked', false)
            ->where(function ($query) use ($threshold) {
                // Content with comments: last comment older than 90 days
                $query->whereHas('comments', function ($q) {
                    // has at least one comment
                })
                ->where(function ($q) use ($threshold) {
                    $q->whereDoesntHave('comments', function ($subQ) use ($threshold) {
                        $subQ->where('created_at', '>', $threshold);
                    });
                });
            })
            ->orWhere(function ($query) use ($threshold) {
                // Content without any comments: content created_at older than 90 days
                $query->where('is_locked', false)
                    ->doesntHave('comments')
                    ->where('created_at', '<=', $threshold);
            })
            ->update([
                'is_locked' => true,
                'locked_at' => Carbon::now(),
            ]);

        Log::info('AutoLockThreads: Locked inactive threads.', [
            'locked_count' => $count,
            'threshold_date' => $threshold->toDateTimeString(),
        ]);
    }
}
