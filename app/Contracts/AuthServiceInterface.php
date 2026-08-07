<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\InviteCode;
use App\Models\User;

interface AuthServiceInterface
{
    public function register(array $data): User;

    public function verifyWithInviteCode(string $userId, string $code): void;

    public function requestAdminVerification(string $userId): void;

    public function approveVerification(string $userId, string $moderatorId, ?string $ip = null): void;

    public function rejectVerification(string $userId, string $reason, ?string $moderatorId = null, ?string $ip = null): void;

    public function generateInviteCode(string $memberId): InviteCode;

    public function lockAccount(string $userId): void;

    public function unlockAccount(string $userId): void;

    public function handleFailedLogin(string $userId, ?string $ip = null): void;

    public function logLogin(string $userId, string $ip): void;

    public function logLogout(string $userId, string $ip): void;

    public function logRegister(string $userId, string $ip): void;
}
