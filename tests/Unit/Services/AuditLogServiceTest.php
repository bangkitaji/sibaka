<?php

namespace Tests\Unit\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AuditLogServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuditLogService $auditLogService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditLogService = new AuditLogService();
    }

    public function testLogCreatesAuditEntry(): void
    {
        $user = User::factory()->create();

        $this->auditLogService->log(
            $user->id,
            'login',
            '192.168.1.1',
            "user:{$user->id}"
        );

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action_type' => 'login',
            'ip_address' => '192.168.1.1',
            'affected_resource' => "user:{$user->id}",
        ]);
    }

    public function testLogCreatesEntryWithMetadata(): void
    {
        $user = User::factory()->create();

        $metadata = ['browser' => 'Chrome', 'os' => 'Linux'];

        $this->auditLogService->log(
            $user->id,
            'login',
            '10.0.0.1',
            "user:{$user->id}",
            $metadata
        );

        $log = AuditLog::where('user_id', $user->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals($metadata, $log->metadata);
    }

    public function testLogCreatesEntryWithNullMetadata(): void
    {
        $user = User::factory()->create();

        $this->auditLogService->log(
            $user->id,
            'logout',
            '192.168.1.1',
            "user:{$user->id}",
            null
        );

        $log = AuditLog::where('user_id', $user->id)->first();
        $this->assertNotNull($log);
        $this->assertNull($log->metadata);
    }

    public function testLogSetsCreatedAtTimestamp(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow(Carbon::create(2024, 6, 15, 10, 30, 0));

        $this->auditLogService->log(
            $user->id,
            'register',
            '192.168.1.1',
            "user:{$user->id}"
        );

        $log = AuditLog::where('user_id', $user->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('2024-06-15 10:30:00', $log->created_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function testLogRejectsEmptyUserId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id must not be empty');

        $this->auditLogService->log('', 'login', '192.168.1.1', 'user:123');
    }

    public function testLogRejectsWhitespaceOnlyUserId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('user_id must not be empty');

        $this->auditLogService->log('   ', 'login', '192.168.1.1', 'user:123');
    }

    public function testLogRejectsEmptyActionType(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('action_type must not be empty');

        $this->auditLogService->log($user->id, '', '192.168.1.1', "user:{$user->id}");
    }

    public function testLogRejectsInvalidActionType(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid action_type: invalid_action');

        $this->auditLogService->log($user->id, 'invalid_action', '192.168.1.1', "user:{$user->id}");
    }

    public function testLogRejectsEmptyIpAddress(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ip_address must not be empty');

        $this->auditLogService->log($user->id, 'login', '', "user:{$user->id}");
    }

    public function testLogRejectsEmptyAffectedResource(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('affected_resource must not be empty');

        $this->auditLogService->log($user->id, 'login', '192.168.1.1', '');
    }

    public function testLogAcceptsAllValidActionTypes(): void
    {
        $user = User::factory()->create();

        foreach (AuditLogService::ACTION_TYPES as $actionType) {
            $this->auditLogService->log(
                $user->id,
                $actionType,
                '192.168.1.1',
                "user:{$user->id}"
            );
        }

        $this->assertEquals(count(AuditLogService::ACTION_TYPES), AuditLog::count());
    }

    public function testPruneOldRecordsDeletesExpiredEntries(): void
    {
        $user = User::factory()->create();

        // Create an old record (400 days ago)
        AuditLog::factory()->create([
            'user_id' => $user->id,
            'created_at' => Carbon::now()->subDays(400),
        ]);

        // Create a recent record (30 days ago)
        AuditLog::factory()->create([
            'user_id' => $user->id,
            'created_at' => Carbon::now()->subDays(30),
        ]);

        $this->assertEquals(2, AuditLog::count());

        $deleted = $this->auditLogService->pruneOldRecords();

        $this->assertEquals(1, $deleted);
        $this->assertEquals(1, AuditLog::count());
    }

    public function testPruneOldRecordsRetainsRecentEntries(): void
    {
        $user = User::factory()->create();

        // Create records within retention period
        AuditLog::factory()->create([
            'user_id' => $user->id,
            'created_at' => Carbon::now()->subDays(364),
        ]);

        AuditLog::factory()->create([
            'user_id' => $user->id,
            'created_at' => Carbon::now()->subDays(1),
        ]);

        $deleted = $this->auditLogService->pruneOldRecords();

        $this->assertEquals(0, $deleted);
        $this->assertEquals(2, AuditLog::count());
    }

    public function testPruneOldRecordsDeletesExactlyAtBoundary(): void
    {
        $user = User::factory()->create();

        // Create record at exactly 366 days (should be deleted)
        AuditLog::factory()->create([
            'user_id' => $user->id,
            'created_at' => Carbon::now()->subDays(366),
        ]);

        // Create record at exactly 365 days (boundary - should be deleted since we use '<')
        AuditLog::factory()->create([
            'user_id' => $user->id,
            'created_at' => Carbon::now()->subDays(365)->subSecond(),
        ]);

        // Create record at 364 days (should be retained)
        AuditLog::factory()->create([
            'user_id' => $user->id,
            'created_at' => Carbon::now()->subDays(364),
        ]);

        $deleted = $this->auditLogService->pruneOldRecords();

        $this->assertEquals(2, $deleted);
        $this->assertEquals(1, AuditLog::count());
    }

    public function testPruneOldRecordsReturnsZeroWhenNothingToDelete(): void
    {
        $deleted = $this->auditLogService->pruneOldRecords();

        $this->assertEquals(0, $deleted);
    }
}
