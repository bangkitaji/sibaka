<?php

namespace Tests\Unit\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneAuditLogsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function testCommandPrunesOldRecords(): void
    {
        $user = User::factory()->create();

        // Create an old record (400 days ago - should be pruned)
        AuditLog::factory()->create([
            'user_id' => $user->id,
            'created_at' => Carbon::now()->subDays(400),
        ]);

        // Create a recent record (30 days ago - should be retained)
        AuditLog::factory()->create([
            'user_id' => $user->id,
            'created_at' => Carbon::now()->subDays(30),
        ]);

        $this->artisan('sibaka:prune-audit-logs')
            ->expectsOutputToContain('Pruned 1 audit log record(s)')
            ->assertExitCode(0);

        $this->assertEquals(1, AuditLog::count());
    }

    public function testCommandOutputsZeroWhenNothingToPrune(): void
    {
        $this->artisan('sibaka:prune-audit-logs')
            ->expectsOutputToContain('Pruned 0 audit log record(s)')
            ->assertExitCode(0);
    }

    public function testCommandRespectsConfiguredRetentionDays(): void
    {
        config(['sibaka.audit_log_retention_days' => 30]);

        $user = User::factory()->create();

        // Create a record 40 days ago (should be pruned with 30-day retention)
        AuditLog::factory()->create([
            'user_id' => $user->id,
            'created_at' => Carbon::now()->subDays(40),
        ]);

        // Create a record 20 days ago (should be retained)
        AuditLog::factory()->create([
            'user_id' => $user->id,
            'created_at' => Carbon::now()->subDays(20),
        ]);

        $this->artisan('sibaka:prune-audit-logs')
            ->expectsOutputToContain('Pruned 1 audit log record(s)')
            ->assertExitCode(0);

        $this->assertEquals(1, AuditLog::count());
    }
}
