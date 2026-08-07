<?php

namespace Tests\Property;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Property 25: Audit Log Completeness
 *
 * *For any* audit log entry, the record SHALL contain all required fields:
 * user ID, action type, timestamp, IP address, and affected resource.
 * No field SHALL be null or empty.
 *
 * **Validates: Requirements 11.6**
 */
class AuditLogCompletenessPropertyTest extends TestCase
{
    use TestTrait;
    use RefreshDatabase;

    private AuditLogService $auditLogService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditLogService = new AuditLogService();
    }

    /**
     * Property: Every created audit log entry has non-null user_id, action_type,
     * ip_address, affected_resource, and created_at.
     *
     * Generate random valid inputs and verify all fields are populated after creation.
     */
    public function testAllAuditLogEntriesHaveCompleteFields(): void
    {
        $this->forAll(
            Generators::elements(...AuditLogService::ACTION_TYPES),
            Generators::elements(
                '192.168.1.1',
                '10.0.0.1',
                '172.16.0.1',
                '203.0.113.42',
                '2001:db8::1',
                '255.255.255.255',
                '127.0.0.1'
            ),
            Generators::elements('user', 'content', 'comment', 'report', 'invite_code'),
        )
            ->then(function (string $actionType, string $ip, string $resourceType) {
                $user = User::factory()->member()->create();
                $affectedResource = "{$resourceType}:{$user->id}";

                $this->auditLogService->log(
                    $user->id,
                    $actionType,
                    $ip,
                    $affectedResource
                );

                $log = AuditLog::where('user_id', $user->id)
                    ->where('action_type', $actionType)
                    ->latest('created_at')
                    ->first();

                $this->assertNotNull($log, 'Audit log entry should exist after creation');
                $this->assertNotNull($log->user_id, 'user_id must not be null');
                $this->assertNotEmpty($log->user_id, 'user_id must not be empty');
                $this->assertNotNull($log->action_type, 'action_type must not be null');
                $this->assertNotEmpty($log->action_type, 'action_type must not be empty');
                $this->assertNotNull($log->ip_address, 'ip_address must not be null');
                $this->assertNotEmpty($log->ip_address, 'ip_address must not be empty');
                $this->assertNotNull($log->affected_resource, 'affected_resource must not be null');
                $this->assertNotEmpty($log->affected_resource, 'affected_resource must not be empty');
                $this->assertNotNull($log->created_at, 'created_at must not be null');

                // Verify the values match what was logged
                $this->assertEquals($user->id, $log->user_id);
                $this->assertEquals($actionType, $log->action_type);
                $this->assertEquals($ip, $log->ip_address);
                $this->assertEquals($affectedResource, $log->affected_resource);
            });
    }

    /**
     * Property: AuditLogService::log() rejects empty/whitespace-only values for all required fields.
     *
     * Generate random whitespace-only or empty strings and verify they are rejected.
     */
    public function testRejectsEmptyUserId(): void
    {
        $this->forAll(
            Generators::elements('', ' ', '  ', "\t", "\n", " \t\n ")
        )
            ->then(function (string $emptyUserId) {
                $this->expectException(InvalidArgumentException::class);
                $this->expectExceptionMessage('user_id must not be empty');

                $this->auditLogService->log(
                    $emptyUserId,
                    'login',
                    '192.168.1.1',
                    'user:some-uuid'
                );
            });
    }

    /**
     * Property: AuditLogService::log() rejects empty action_type values.
     */
    public function testRejectsEmptyActionType(): void
    {
        $this->forAll(
            Generators::elements('', ' ', '  ', "\t", "\n", " \t\n ")
        )
            ->then(function (string $emptyActionType) {
                $user = User::factory()->member()->create();

                $this->expectException(InvalidArgumentException::class);
                $this->expectExceptionMessage('action_type must not be empty');

                $this->auditLogService->log(
                    $user->id,
                    $emptyActionType,
                    '192.168.1.1',
                    'user:some-uuid'
                );
            });
    }

    /**
     * Property: AuditLogService::log() rejects empty ip_address values.
     */
    public function testRejectsEmptyIpAddress(): void
    {
        $this->forAll(
            Generators::elements('', ' ', '  ', "\t", "\n", " \t\n ")
        )
            ->then(function (string $emptyIp) {
                $user = User::factory()->member()->create();

                $this->expectException(InvalidArgumentException::class);
                $this->expectExceptionMessage('ip_address must not be empty');

                $this->auditLogService->log(
                    $user->id,
                    'login',
                    $emptyIp,
                    'user:some-uuid'
                );
            });
    }

    /**
     * Property: AuditLogService::log() rejects empty affected_resource values.
     */
    public function testRejectsEmptyAffectedResource(): void
    {
        $this->forAll(
            Generators::elements('', ' ', '  ', "\t", "\n", " \t\n ")
        )
            ->then(function (string $emptyResource) {
                $user = User::factory()->member()->create();

                $this->expectException(InvalidArgumentException::class);
                $this->expectExceptionMessage('affected_resource must not be empty');

                $this->auditLogService->log(
                    $user->id,
                    'login',
                    '192.168.1.1',
                    $emptyResource
                );
            });
    }

    /**
     * Property: Only valid action_types are accepted.
     *
     * Generate random invalid action type strings and verify they are rejected.
     */
    public function testRejectsInvalidActionTypes(): void
    {
        $this->forAll(
            Generators::elements(
                'invalid_action',
                'hack',
                'delete_everything',
                'admin_bypass',
                'LOGIN',
                'Login',
                'log_in',
                'sign_up',
                'content_create',
                'content_update'
            )
        )
            ->then(function (string $invalidActionType) {
                $user = User::factory()->member()->create();

                $this->expectException(InvalidArgumentException::class);
                $this->expectExceptionMessage('Invalid action_type');

                $this->auditLogService->log(
                    $user->id,
                    $invalidActionType,
                    '192.168.1.1',
                    'user:some-uuid'
                );
            });
    }

    /**
     * Property: All valid action types are accepted and create complete log entries.
     *
     * Iterate through all valid action types and verify each creates a complete entry.
     */
    public function testAllValidActionTypesCreateCompleteEntries(): void
    {
        $this->forAll(
            Generators::elements(...AuditLogService::ACTION_TYPES)
        )
            ->then(function (string $actionType) {
                $user = User::factory()->member()->create();
                $ip = '10.0.0.1';
                $resource = "user:{$user->id}";

                $this->auditLogService->log($user->id, $actionType, $ip, $resource);

                $log = AuditLog::where('user_id', $user->id)
                    ->where('action_type', $actionType)
                    ->latest('created_at')
                    ->first();

                $this->assertNotNull($log, "Audit log entry should exist for action_type: {$actionType}");
                $this->assertEquals($user->id, $log->user_id);
                $this->assertEquals($actionType, $log->action_type);
                $this->assertEquals($ip, $log->ip_address);
                $this->assertEquals($resource, $log->affected_resource);
                $this->assertNotNull($log->created_at);
            });
    }
}
