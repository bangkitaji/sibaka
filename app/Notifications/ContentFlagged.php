<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContentFlagged extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The backoff strategy (in seconds) between retry attempts.
     *
     * @var array<int, int>
     */
    public array $backoff = [30, 60, 120];

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly string $contentId,
        private readonly string $contentTitle,
        private readonly string $reason,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Content Flagged for Review - SIBAKA Portal')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A piece of content has been flagged for moderation review.')
            ->line("**Content:** {$this->contentTitle}")
            ->line("**Reason:** {$this->reason}")
            ->line('Please review this content in the moderation queue at your earliest convenience.')
            ->action('View Moderation Queue', url('/moderation/queue'))
            ->line('This is an automated notification from the SIBAKA moderation system.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'content_id' => $this->contentId,
            'content_title' => $this->contentTitle,
            'reason' => $this->reason,
        ];
    }
}
