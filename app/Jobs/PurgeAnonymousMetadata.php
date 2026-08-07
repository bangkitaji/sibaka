<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\AnonymityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PurgeAnonymousMetadata implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(AnonymityService $anonymityService): void
    {
        $count = $anonymityService->purgeExpiredMetadata();

        Log::info('PurgeAnonymousMetadata: Purged expired anonymous metadata records.', [
            'deleted_count' => $count,
        ]);
    }
}
