<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewContentReport extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly Report $report,
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
        $reason = $this->report->reason->value;
        $contentTitle = $this->report->content?->title ?? 'Unknown Content';

        return (new MailMessage())
            ->subject('New Content Report - SIBAKA Portal')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A new content report has been submitted that requires your review.')
            ->line("**Content:** {$contentTitle}")
            ->line("**Reason:** {$reason}")
            ->line("**Description:** " . ($this->report->description ?? 'No description provided.'))
            ->action('Review in Moderation Queue', url('/moderation/queue'))
            ->line('Please review this report within 24 hours.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'report_id' => $this->report->id,
            'content_id' => $this->report->content_id,
            'reason' => $this->report->reason->value,
        ];
    }
}
