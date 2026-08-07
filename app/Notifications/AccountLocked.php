<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountLocked extends Notification implements ShouldQueue
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
        private readonly int $lockDurationMinutes,
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
            ->subject('Account Locked - SIBAKA Portal')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your SIBAKA Portal account has been locked due to too many failed login attempts.')
            ->line("Your account will be automatically unlocked after {$this->lockDurationMinutes} minutes.")
            ->line('If you did not attempt to log in, please secure your account by changing your password once the lock expires.')
            ->action('Visit SIBAKA Portal', url('/'))
            ->line('If you need immediate assistance, please contact the moderation team.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'lock_duration_minutes' => $this->lockDurationMinutes,
            'reason' => 'Too many failed login attempts',
        ];
    }
}
