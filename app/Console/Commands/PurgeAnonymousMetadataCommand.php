<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AnonymityService;
use Illuminate\Console\Command;

class PurgeAnonymousMetadataCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sibaka:purge-anonymous-metadata';

    /**
     * The console command description.
     */
    protected $description = 'Purge anonymous metadata records older than 90 days';

    /**
     * Execute the console command.
     */
    public function handle(AnonymityService $anonymityService): int
    {
        $count = $anonymityService->purgeExpiredMetadata();

        $this->info("Purged {$count} expired anonymous metadata record(s).");

        return self::SUCCESS;
    }
}
