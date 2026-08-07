<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Content;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AutoLockThreadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sibaka:auto-lock-threads';

    /**
     * The console command description.
     */
    protected $description = 'Lock threads with no activity for 90 days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $threshold = Carbon::now()->subDays(90);

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

        $this->info("Locked {$count} inactive thread(s) with no activity since {$threshold->toDateString()}.");

        return self::SUCCESS;
    }
}
