<?php

declare(strict_types=1);

namespace App\Http\Controllers\Moderation;

use App\Contracts\ModerationServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReportContentRequest;
use App\Models\Report;
use App\Models\User;
use App\Notifications\NewContentReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

class ReportController extends Controller
{
    public function __construct(
        protected ModerationServiceInterface $moderationService
    ) {}

    /**
     * Submit a report against a content item.
     * POST /api/v1/moderation/reports
     */
    public function store(ReportContentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $contentId = $data['content_id'];
        $reporterId = $request->user()->id;

        $this->moderationService->reportContent(
            $contentId,
            $reporterId,
            $data['reason'],
            $data['description'] ?? null
        );

        // Notify moderators within 30 seconds (queued on notifications queue)
        $report = Report::where('content_id', $contentId)
            ->where('reporter_id', $reporterId)
            ->latest()
            ->first();

        if ($report) {
            $moderators = User::moderators()->get();
            Notification::send($moderators, new NewContentReport($report));
        }

        return response()->json([
            'message' => 'Content reported successfully. Moderators have been notified.',
        ], 201);
    }
}
