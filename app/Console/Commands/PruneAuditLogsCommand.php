<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AuditLogService;
use Illuminate\Console\Command;

class PruneAuditLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sibaka:prune-audit-logs';

    /**
     * The console command description.
     */
    protected $description = 'Delete audit log records older than 365 days (configurable via sibaka.audit_log_retention_days)';

    /**
     * Execute the console command.
     */
    public function handle(AuditLogService $auditLogService): int
    {
        $count = $auditLogService->pruneOldRecords();

        $this->info("Pruned {$count} audit log record(s) older than " . config('sibaka.audit_log_retention_days', 365) . " days.");

        return self::SUCCESS;
    }
}
