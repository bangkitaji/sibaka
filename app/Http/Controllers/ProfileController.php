<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\DirectoryServiceInterface;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private readonly DirectoryServiceInterface $directoryService,
    ) {}

    /**
     * Show the authenticated user's profile edit page.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();
        $completion = $this->directoryService->getProfileCompletion($user->id);
        $profile = $user->profile;

        return Inertia::render('Profile/Edit', [
            'profile' => $profile,
            'completion' => $completion,
        ]);
    }

    /**
     * Update the authenticated user's profile.
     */
    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();

        $profile = $this->directoryService->updateProfile($user->id, $validated);
        $completion = $this->directoryService->getProfileCompletion($user->id);

        return redirect()->route('profile.show')->with([
            'success' => 'Profile updated successfully.',
            'completion' => $completion,
        ]);
    }
}
