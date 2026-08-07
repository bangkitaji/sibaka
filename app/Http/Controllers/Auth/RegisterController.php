<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Contracts\AuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function __construct(
        private readonly AuthServiceInterface $authService,
    ) {}

    /**
     * Display the registration form.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle a registration request.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = $this->authService->register($validated);

        if (! empty($validated['invite_code'])) {
            $this->authService->verifyWithInviteCode($user->id, $validated['invite_code']);
            $user->refresh();
        }

        $this->authService->logRegister($user->id, $request->ip());

        Auth::login($user);

        return redirect()->intended('/');
    }
}
