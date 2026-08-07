<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InviteCodeController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    /**
     * Generate a new invite code (member-only).
     */
    public function store(Request $request): JsonResponse
    {
        $inviteCode = $this->authService->generateInviteCode($request->user()->id);

        return response()->json([
            'code' => $inviteCode->code,
            'expires_at' => $inviteCode->expires_at,
        ], 201);
    }
}
