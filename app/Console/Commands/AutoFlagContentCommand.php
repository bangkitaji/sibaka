<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ContentStatus;
use App\Jobs\AutoFlagContent;
use App\Models\Content;
use Illuminate\Console\Command;

class AutoFlagContentCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sibaka:auto-flag-content
                            {--content-id= : Check a specific content item by ID}
                            {--all : Check all published content}
                            {--since= : Check content published since a date (Y-m-d format)}';

    /**
     * The console command description.
     */
    protected $description = 'Manually run auto-flagging checks on published content for swear words, spam, and malicious links';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $contentId = $this->option('content-id');
        $all = $this->option('all');
        $since = $this->option('since');

        if ($contentId) {
            return $this->checkSingleContent($contentId);
        }

        if ($all || $since) {
            return $this->batchCheck($since);
        }

        $this->error('Please provide --content-id, --all, or --since option.');
        $this->line('');
        $this->line('Usage examples:');
        $this->line('  php artisan sibaka:auto-flag-content --content-id=<uuid>');
        $this->line('  php artisan sibaka:auto-flag-content --all');
        $this->line('  php artisan sibaka:auto-flag-content --since=2024-01-01');

        return self::FAILURE;
    }

    /**
     * Check a single content item.
     */
    protected function checkSingleContent(string $contentId): int
    {
        $content = Content::find($contentId);

        if (!$content) {
            $this->error("Content not found: {$contentId}");
            return self::FAILURE;
        }

        $this->info("Dispatching auto-flag check for content: {$content->title}");
        AutoFlagContent::dispatch($content->id);

        $this->info('Auto-flag job dispatched successfully.');
        return self::SUCCESS;
    }

    /**
     * Batch check published content.
     */
    protected function batchCheck(?string $since): int
    {
        $query = Content::where('status', ContentStatus::Published);

        if ($since) {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $since);
            if (!$date) {
                $this->error("Invalid date format. Use Y-m-d (e.g., 2024-01-01).");
                return self::FAILURE;
            }
            $query->where('published_at', '>=', $date->format('Y-m-d 00:00:00'));
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('No published content found matching criteria.');
            return self::SUCCESS;
        }

        $this->info("Dispatching auto-flag checks for {$count} content item(s)...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->chunkById(100, function ($contents) use ($bar) {
            foreach ($contents as $content) {
                AutoFlagContent::dispatch($content->id);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Auto-flag jobs dispatched for {$count} content item(s).");

        return self::SUCCESS;
    }
}
