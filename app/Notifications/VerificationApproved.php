<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerificationApproved extends Notification implements ShouldQueue
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
    public function __construct()
    {
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
            ->subject('Your SIBAKA account has been verified')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Congratulations! Your SIBAKA Portal account has been verified.')
            ->line('You now have full member access to the platform, including:')
            ->line('- Creating and sharing content with the community')
            ->line('- Accessing the alumni IT directory')
            ->line('- Participating in discussions and Q&A')
            ->line('- Reacting to and commenting on posts')
            ->action('Go to SIBAKA Portal', url('/'))
            ->line('Welcome to the SIBAKA community!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'status' => 'approved',
        ];
    }
}
