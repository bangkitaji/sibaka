<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\PortalMessage;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Listens for failed email notifications and creates in-app messages
 * to inform users when email delivery has failed persistently.
 *
 * This implements the graceful degradation requirement: when the email
 * service is down, users are still notified through the portal messaging system.
 */
class HandleFailedEmailNotification
{
    /**
     * Handle the event.
     */
    public function handle(NotificationFailed $event): void
    {
        // Only handle mail channel failures
        if ($event->channel !== 'mail') {
            return;
        }

        $notifiable = $event->notifiable;

        // Only process User model notifiables
        if (! ($notifiable instanceof \App\Models\User)) {
            return;
        }

        Log::warning('Email notification failed, creating in-app notification as fallback', [
            'user_id' => $notifiable->id,
            'notification_type' => get_class($event->notification),
            'channel' => $event->channel,
        ]);

        $this->createInAppFallbackMessage($notifiable, $event->notification);
    }

    /**
     * Create a portal message as a fallback when email delivery fails.
     */
    protected function createInAppFallbackMessage(
        \App\Models\User $user,
        mixed $notification
    ): void {
        $notificationType = class_basename($notification);
        $friendlyName = Str::headline($notificationType);

        PortalMessage::create([
            'id' => Str::uuid()->toString(),
            'sender_id' => $user->id, // System message sent to self
            'recipient_id' => $user->id,
            'body' => "[System] We were unable to deliver an email notification ({$friendlyName}) to your registered email address. "
                . 'The system will retry delivery automatically. If this persists, please check your email settings in your profile.',
            'is_read' => false,
        ]);
    }
}
