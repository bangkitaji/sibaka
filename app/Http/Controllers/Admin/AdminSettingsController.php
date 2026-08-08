<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingsController extends Controller
{
    public function index(): Response
    {
        $settings = Setting::all()->keyBy('key');

        return Inertia::render('Admin/Settings/Index', [
            'settings' => [
                'app_name' => $settings->get('app_name')?->value ?? 'SIBAKA Portal',
                'app_description' => $settings->get('app_description')?->value ?? '',
                'maintenance_mode' => filter_var($settings->get('maintenance_mode')?->value ?? '0', FILTER_VALIDATE_BOOLEAN),
                'registration_enabled' => filter_var($settings->get('registration_enabled')?->value ?? '1', FILTER_VALIDATE_BOOLEAN),
                'invite_code_required' => filter_var($settings->get('invite_code_required')?->value ?? '0', FILTER_VALIDATE_BOOLEAN),
                'max_failed_login_attempts' => (int) ($settings->get('max_failed_login_attempts')?->value ?? 5),
                'auto_approve_content' => filter_var($settings->get('auto_approve_content')?->value ?? '1', FILTER_VALIDATE_BOOLEAN),
                'allow_anonymous_posts' => filter_var($settings->get('allow_anonymous_posts')?->value ?? '1', FILTER_VALIDATE_BOOLEAN),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'app_description' => ['nullable', 'string', 'max:1000'],
            'maintenance_mode' => ['required', 'boolean'],
            'registration_enabled' => ['required', 'boolean'],
            'invite_code_required' => ['required', 'boolean'],
            'max_failed_login_attempts' => ['required', 'integer', 'min:1', 'max:20'],
            'auto_approve_content' => ['required', 'boolean'],
            'allow_anonymous_posts' => ['required', 'boolean'],
        ]);

        Setting::set('app_name', $validated['app_name'], 'string', 'general');
        Setting::set('app_description', $validated['app_description'] ?? '', 'string', 'general');
        Setting::set('maintenance_mode', $validated['maintenance_mode'], 'boolean', 'general');

        Setting::set('registration_enabled', $validated['registration_enabled'], 'boolean', 'auth');
        Setting::set('invite_code_required', $validated['invite_code_required'], 'boolean', 'auth');
        Setting::set('max_failed_login_attempts', $validated['max_failed_login_attempts'], 'integer', 'auth');

        Setting::set('auto_approve_content', $validated['auto_approve_content'], 'boolean', 'content');
        Setting::set('allow_anonymous_posts', $validated['allow_anonymous_posts'], 'boolean', 'content');

        return redirect()->back()->with('status', 'Application settings updated successfully.');
    }
}
