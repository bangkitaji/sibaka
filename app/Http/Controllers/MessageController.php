<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreMessageRequest;
use App\Models\PortalMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    /**
     * List messages for the authenticated user (received messages).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $messages = PortalMessage::where('recipient_id', $user->id)
            ->with('sender:id,name')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($messages);
    }

    /**
     * Send a new portal message.
     *
     * Validates:
     * - body: required, string, max 1000 chars
     * - Rate limit: max 10 messages per day per sender
     */
    public function store(StoreMessageRequest $request): JsonResponse
    {
        $sender = $request->user();
        $validated = $request->validated();

        // Check rate limit: max 10 messages per day per sender
        $messageCountToday = PortalMessage::where('sender_id', $sender->id)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($messageCountToday >= 10) {
            return response()->json([
                'message' => 'You have reached the maximum of 10 messages per day.',
            ], 429);
        }

        // Prevent sending messages to self
        if ($validated['recipient_id'] === $sender->id) {
            return response()->json([
                'message' => 'You cannot send a message to yourself.',
            ], 422);
        }

        $message = PortalMessage::create([
            'sender_id' => $sender->id,
            'recipient_id' => $validated['recipient_id'],
            'body' => $validated['body'],
            'is_read' => false,
        ]);

        $message->load('sender:id,name', 'recipient:id,name');

        return response()->json([
            'message' => 'Message sent successfully.',
            'data' => $message,
        ], 201);
    }

    /**
     * Mark a message as read.
     */
    public function markAsRead(Request $request, PortalMessage $message): JsonResponse
    {
        $user = $request->user();

        // Only the recipient can mark a message as read
        if ($message->recipient_id !== $user->id) {
            return response()->json([
                'message' => 'You are not authorized to mark this message as read.',
            ], 403);
        }

        $message->update(['is_read' => true]);

        return response()->json([
            'message' => 'Message marked as read.',
            'data' => $message,
        ]);
    }

    /**
     * Mark a message as unread.
     */
    public function markAsUnread(Request $request, PortalMessage $message): JsonResponse
    {
        $user = $request->user();

        // Only the recipient can mark a message as unread
        if ($message->recipient_id !== $user->id) {
            return response()->json([
                'message' => 'You are not authorized to mark this message as unread.',
            ], 403);
        }

        $message->update(['is_read' => false]);

        return response()->json([
            'message' => 'Message marked as unread.',
            'data' => $message,
        ]);
    }
}
