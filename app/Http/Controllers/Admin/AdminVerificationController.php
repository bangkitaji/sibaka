<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\VerificationApproved;
use App\Notifications\VerificationRejected;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminVerificationController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function index(Request $request): Response
    {
        $statusFilter = $request->input('status', VerificationStatus::Pending->value);

        $users = User::where('verification_status', $statusFilter)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Verification/Index', [
            'users' => $users,
            'currentStatus' => $statusFilter,
            'statuses' => VerificationStatus::cases(),
        ]);
    }

    public function approve(Request $request, string $userId): RedirectResponse
    {
        $this->authService->approveVerification($userId, $request->user()->id, $request->ip());

        $user = User::findOrFail($userId);
        $user->notify(new VerificationApproved());

        return redirect()->back()->with('status', "Verification approved for {$user->name}.");
    }

    public function reject(Request $request, string $userId): RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $reason = $request->input('reason');

        $this->authService->rejectVerification($userId, $reason, $request->user()->id, $request->ip());

        $user = User::findOrFail($userId);
        $user->notify(new VerificationRejected($reason));

        return redirect()->back()->with('status', "Verification rejected for {$user->name}.");
    }
}
