<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountSuspended extends Notification implements ShouldQueue
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
        private readonly int $days,
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
        $suspendedUntil = now()->addDays($this->days)->format('F j, Y \a\t g:i A');

        return (new MailMessage())
            ->subject('Account Suspended - SIBAKA Portal')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your SIBAKA Portal account has been suspended.')
            ->line("**Duration:** {$this->days} day(s)")
            ->line("**Until:** {$suspendedUntil}")
            ->line("**Reason:** {$this->reason}")
            ->line('During the suspension period, you will retain read-only access to public content but will not be able to post, comment, react, or send messages.')
            ->action('Visit SIBAKA Portal', url('/'))
            ->line('If you believe this action was taken in error, please contact the moderation team.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'days' => $this->days,
            'reason' => $this->reason,
        ];
    }
}
