<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Carbon\Carbon;
use InvalidArgumentException;

class AuditLogService
{
    /**
     * Valid action types for audit logging.
     */
    public const ACTION_TYPES = [
        'login',
        'logout',
        'login_failed',
        'register',
        'verification_approved',
        'verification_rejected',
        'content_created',
        'content_deleted',
        'moderation_action',
        'user_suspended',
        'warning_issued',
    ];

    /**
     * Log an audit event.
     *
     * All fields are required and must be non-null/non-empty.
     *
     * @param string $userId The user who triggered the action
     * @param string $actionType The type of action performed
     * @param string $ip The IP address of the request
     * @param string $affectedResource The resource affected (e.g. "user:uuid", "content:uuid")
     * @param array|null $metadata Optional additional metadata
     *
     * @throws InvalidArgumentException If any required field is empty or action_type is invalid
     */
    public function log(string $userId, string $actionType, string $ip, string $affectedResource, ?array $metadata = null): void
    {
        $this->validate($userId, $actionType, $ip, $affectedResource);

        AuditLog::create([
            'user_id' => $userId,
            'action_type' => $actionType,
            'ip_address' => $ip,
            'affected_resource' => $affectedResource,
            'metadata' => $metadata,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Prune audit log records older than the configured retention period.
     *
     * @return int The number of records deleted
     */
    public function pruneOldRecords(): int
    {
        $retentionDays = (int) config('sibaka.audit_log_retention_days', 365);

        $cutoff = Carbon::now()->subDays($retentionDays);

        return AuditLog::where('created_at', '<', $cutoff)->delete();
    }

    /**
     * Validate all required fields are non-null and non-empty.
     *
     * @throws InvalidArgumentException
     */
    private function validate(string $userId, string $actionType, string $ip, string $affectedResource): void
    {
        if (trim($userId) === '') {
            throw new InvalidArgumentException('user_id must not be empty.');
        }

        if (trim($actionType) === '') {
            throw new InvalidArgumentException('action_type must not be empty.');
        }

        if (!in_array($actionType, self::ACTION_TYPES, true)) {
            throw new InvalidArgumentException("Invalid action_type: {$actionType}. Must be one of: " . implode(', ', self::ACTION_TYPES));
        }

        if (trim($ip) === '') {
            throw new InvalidArgumentException('ip_address must not be empty.');
        }

        if (trim($affectedResource) === '') {
            throw new InvalidArgumentException('affected_resource must not be empty.');
        }
    }
}
