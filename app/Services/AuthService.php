<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AuthServiceInterface;
use App\Models\InviteCode;
use App\Models\User;
use App\Notifications\AccountLocked;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class AuthService implements AuthServiceInterface
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function register(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'entry_year' => $data['entry_year'],
            'graduation_year' => $data['graduation_year'],
            'department' => $data['department'],
        ]);
    }

    public function verifyWithInviteCode(string $userId, string $code): void
    {
        $inviteCode = InviteCode::where('code', $code)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $inviteCode->update([
            'is_used' => true,
            'used_by' => $userId,
        ]);

        $user = User::findOrFail($userId);
        $user->update([
            'role' => 'member',
            'verification_status' => 'approved',
        ]);
    }

    public function requestAdminVerification(string $userId): void
    {
        $user = User::findOrFail($userId);
        $user->update([
            'verification_status' => 'pending',
        ]);
    }

    public function approveVerification(string $userId, string $moderatorId, ?string $ip = null): void
    {
        $user = User::findOrFail($userId);
        $user->update([
            'role' => 'member',
            'verification_status' => 'approved',
        ]);

        if ($ip) {
            $this->auditLogService->log(
                $moderatorId,
                'verification_approved',
                $ip,
                "user:{$userId}",
            );
        }
    }

    public function rejectVerification(string $userId, string $reason, ?string $moderatorId = null, ?string $ip = null): void
    {
        $user = User::findOrFail($userId);
        $user->update([
            'verification_status' => 'rejected',
        ]);

        if ($ip && $moderatorId) {
            $this->auditLogService->log(
                $moderatorId,
                'verification_rejected',
                $ip,
                "user:{$userId}",
                ['reason' => $reason]
            );
        }
    }

    public function generateInviteCode(string $memberId): InviteCode
    {
        return InviteCode::create([
            'generated_by' => $memberId,
            'code' => bin2hex(random_bytes(16)),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function handleFailedLogin(string $userId, ?string $ip = null): void
    {
        $user = User::findOrFail($userId);

        $maxAttempts = config('sibaka.max_failed_login_attempts', 5);
        $windowMinutes = config('sibaka.failed_login_window_minutes', 15);
        $lockDurationMinutes = config('sibaka.account_lock_duration_minutes', 30);

        $newAttempts = $user->failed_login_attempts + 1;

        $user->update([
            'failed_login_attempts' => $newAttempts,
        ]);

        if ($ip) {
            $this->auditLogService->log(
                $userId,
                'login_failed',
                $ip,
                "user:{$userId}",
                ['attempt_number' => $newAttempts]
            );
        }

        if ($newAttempts >= $maxAttempts) {
            $this->lockAccount($userId);
        }
    }

    public function lockAccount(string $userId): void
    {
        $lockDurationMinutes = config('sibaka.account_lock_duration_minutes', 30);

        $user = User::findOrFail($userId);
        $user->update([
            'locked_until' => now()->addMinutes($lockDurationMinutes),
            'failed_login_attempts' => 0,
        ]);

        $user->notify(new AccountLocked($lockDurationMinutes));
    }

    public function unlockAccount(string $userId): void
    {
        $user = User::findOrFail($userId);
        $user->update([
            'locked_until' => null,
            'failed_login_attempts' => 0,
        ]);
    }

    /**
     * Log a successful login event.
     */
    public function logLogin(string $userId, string $ip): void
    {
        $this->auditLogService->log($userId, 'login', $ip, "user:{$userId}");
    }

    /**
     * Log a logout event.
     */
    public function logLogout(string $userId, string $ip): void
    {
        $this->auditLogService->log($userId, 'logout', $ip, "user:{$userId}");
    }

    /**
     * Log a registration event.
     */
    public function logRegister(string $userId, string $ip): void
    {
        $this->auditLogService->log($userId, 'register', $ip, "user:{$userId}");
    }
}
