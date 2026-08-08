<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ContentStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Content;
use App\Models\InviteCode;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $stats = [
            'total_users' => User::count(),
            'users_by_role' => [
                'admin' => User::where('role', UserRole::Admin)->count(),
                'moderator' => User::where('role', UserRole::Moderator)->count(),
                'member' => User::where('role', UserRole::Member)->count(),
                'pending' => User::where('role', UserRole::Pending)->count(),
            ],
            'pending_verifications' => User::where('verification_status', VerificationStatus::Pending)->count(),
            'suspended_users' => User::where('is_suspended', true)->count(),
            'total_content' => Content::count(),
            'published_content' => Content::where('status', ContentStatus::Published)->count(),
            'pending_content' => Content::where('status', ContentStatus::PendingReview)->count(),
            'total_comments' => Comment::count(),
            'pending_reports' => Report::where('status', 'pending')->count(),
            'total_invite_codes' => InviteCode::count(),
            'valid_invite_codes' => InviteCode::valid()->count(),
        ];

        $recentUsers = User::latest()
            ->take(5)
            ->select(['id', 'name', 'email', 'role', 'verification_status', 'created_at'])
            ->get();

        $recentContent = Content::with('author:id,name')
            ->latest()
            ->take(5)
            ->select(['id', 'author_id', 'title', 'category', 'status', 'created_at'])
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'recentUsers' => $recentUsers,
            'recentContent' => $recentContent,
        ]);
    }
}
