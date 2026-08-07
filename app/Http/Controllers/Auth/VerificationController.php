<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\VerificationApproved;
use App\Notifications\VerificationRejected;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VerificationController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    /**
     * Verify the user's invite code.
     */
    public function verifyInvite(Request $request): RedirectResponse
    {
        $request->validate([
            'invite_code' => ['required', 'string'],
        ]);

        $this->authService->verifyWithInviteCode(
            $request->user()->id,
            $request->input('invite_code'),
        );

        return redirect()->route('home')->with('status', 'Your account has been verified.');
    }

    /**
     * Show the verification pending page.
     */
    public function showPending(Request $request): Response
    {
        return Inertia::render('Auth/VerifyPending', [
            'verificationStatus' => $request->user()->verification_status,
        ]);
    }

    /**
     * Approve a user's verification (admin/moderator action).
     */
    public function approve(Request $request, string $userId): RedirectResponse
    {
        $this->authService->approveVerification($userId, $request->user()->id, $request->ip());

        $user = User::findOrFail($userId);
        $user->notify(new VerificationApproved());

        return redirect()->back()->with('status', 'User verification approved.');
    }

    /**
     * Reject a user's verification (admin/moderator action).
     */
    public function reject(Request $request, string $userId): RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $reason = $request->input('reason');

        $this->authService->rejectVerification($userId, $reason, $request->user()->id, $request->ip());

        $user = User::findOrFail($userId);
        $user->notify(new VerificationRejected($reason));

        return redirect()->back()->with('status', 'User verification rejected.');
    }
}
