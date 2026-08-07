<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerificationRejected extends Notification implements ShouldQueue
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
            ->subject('SIBAKA verification status update')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('We regret to inform you that your SIBAKA Portal verification request has been rejected.')
            ->line('Reason: ' . $this->reason)
            ->line('If you believe this is a mistake, you may submit an appeal by contacting the moderation team.')
            ->action('Contact Support', url('/'))
            ->line('Thank you for your interest in the SIBAKA community.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'status' => 'rejected',
            'reason' => $this->reason,
        ];
    }
}
