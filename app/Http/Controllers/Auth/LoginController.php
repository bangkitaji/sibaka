<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Contracts\AuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function __construct(
        private readonly AuthServiceInterface $authService,
    ) {}

    /**
     * Display the login form.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Handle a login request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return back()->withErrors([
                'email' => __('These credentials do not match our records.'),
            ])->onlyInput('email');
        }

        // Check if account is locked
        if ($user->locked_until && Carbon::parse($user->locked_until)->isFuture()) {
            $remainingMinutes = (int) now()->diffInMinutes($user->locked_until, false);

            return back()->withErrors([
                'email' => __('Your account is locked due to too many failed login attempts. Please try again in :minutes minutes.', [
                    'minutes' => max($remainingMinutes, 1),
                ]),
            ])->onlyInput('email');
        }

        // If lock has expired, reset the lock
        if ($user->locked_until && Carbon::parse($user->locked_until)->isPast()) {
            $this->authService->unlockAccount($user->id);
            $user->refresh();
        }

        // Attempt authentication
        if (! Auth::attempt($validated, $request->boolean('remember'))) {
            $this->authService->handleFailedLogin($user->id, $request->ip());

            return back()->withErrors([
                'email' => __('These credentials do not match our records.'),
            ])->onlyInput('email');
        }

        // Successful login: reset failed attempts and update last_login_at
        $user->update([
            'failed_login_attempts' => 0,
            'last_login_at' => now(),
        ]);

        $this->authService->logLogin($user->id, $request->ip());

        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    /**
     * Handle a logout request.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user) {
            $this->authService->logLogout($user->id, $request->ip());
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
