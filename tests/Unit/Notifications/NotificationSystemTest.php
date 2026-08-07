<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\User;
use App\Notifications\AccountLocked;
use App\Notifications\AccountSuspended;
use App\Notifications\ContentFlagged;
use App\Notifications\VerificationApproved;
use App\Notifications\VerificationRejected;
use Illuminate\Contracts\Queue\ShouldQueue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{

    #[Test]
    public function verification_approved_implements_should_queue(): void
    {
        $notification = new VerificationApproved();
        $this->assertInstanceOf(ShouldQueue::class, $notification);
    }

    #[Test]
    public function verification_approved_uses_notifications_queue(): void
    {
        $notification = new VerificationApproved();
        $this->assertEquals('notifications', $notification->queue);
    }

    #[Test]
    public function verification_approved_has_correct_retry_configuration(): void
    {
        $notification = new VerificationApproved();
        $this->assertEquals(3, $notification->tries);
        $this->assertEquals([30, 60, 120], $notification->backoff);
    }

    #[Test]
    public function verification_approved_sends_via_mail(): void
    {
        $notification = new VerificationApproved();
        $user = User::factory()->make();
        $this->assertEquals(['mail'], $notification->via($user));
    }

    #[Test]
    public function verification_rejected_implements_should_queue(): void
    {
        $notification = new VerificationRejected('Test reason');
        $this->assertInstanceOf(ShouldQueue::class, $notification);
    }

    #[Test]
    public function verification_rejected_uses_notifications_queue(): void
    {
        $notification = new VerificationRejected('Test reason');
        $this->assertEquals('notifications', $notification->queue);
    }

    #[Test]
    public function verification_rejected_has_correct_retry_configuration(): void
    {
        $notification = new VerificationRejected('Test reason');
        $this->assertEquals(3, $notification->tries);
        $this->assertEquals([30, 60, 120], $notification->backoff);
    }

    #[Test]
    public function account_suspended_implements_should_queue(): void
    {
        $notification = new AccountSuspended(7, 'Violation');
        $this->assertInstanceOf(ShouldQueue::class, $notification);
    }

    #[Test]
    public function account_suspended_uses_notifications_queue(): void
    {
        $notification = new AccountSuspended(7, 'Violation');
        $this->assertEquals('notifications', $notification->queue);
    }

    #[Test]
    public function account_suspended_has_correct_retry_configuration(): void
    {
        $notification = new AccountSuspended(7, 'Violation');
        $this->assertEquals(3, $notification->tries);
        $this->assertEquals([30, 60, 120], $notification->backoff);
    }

    #[Test]
    public function account_locked_implements_should_queue(): void
    {
        $notification = new AccountLocked(30);
        $this->assertInstanceOf(ShouldQueue::class, $notification);
    }

    #[Test]
    public function account_locked_uses_notifications_queue(): void
    {
        $notification = new AccountLocked(30);
        $this->assertEquals('notifications', $notification->queue);
    }

    #[Test]
    public function account_locked_has_correct_retry_configuration(): void
    {
        $notification = new AccountLocked(30);
        $this->assertEquals(3, $notification->tries);
        $this->assertEquals([30, 60, 120], $notification->backoff);
    }

    #[Test]
    public function content_flagged_implements_should_queue(): void
    {
        $notification = new ContentFlagged('id-123', 'Title', 'spam');
        $this->assertInstanceOf(ShouldQueue::class, $notification);
    }

    #[Test]
    public function content_flagged_uses_notifications_queue(): void
    {
        $notification = new ContentFlagged('id-123', 'Title', 'spam');
        $this->assertEquals('notifications', $notification->queue);
    }

    #[Test]
    public function content_flagged_has_correct_retry_configuration(): void
    {
        $notification = new ContentFlagged('id-123', 'Title', 'spam');
        $this->assertEquals(3, $notification->tries);
        $this->assertEquals([30, 60, 120], $notification->backoff);
    }

    #[Test]
    public function content_flagged_sends_via_mail(): void
    {
        $notification = new ContentFlagged('id-123', 'Title', 'spam');
        $user = User::factory()->make();
        $this->assertEquals(['mail'], $notification->via($user));
    }

    #[Test]
    public function content_flagged_to_array_returns_correct_data(): void
    {
        $notification = new ContentFlagged('id-123', 'My Post', 'spam');
        $user = User::factory()->make();
        $data = $notification->toArray($user);

        $this->assertEquals([
            'content_id' => 'id-123',
            'content_title' => 'My Post',
            'reason' => 'spam',
        ], $data);
    }

    #[Test]
    public function content_flagged_mail_contains_content_details(): void
    {
        $notification = new ContentFlagged('id-123', 'My Post', 'harassment');
        $user = User::factory()->make(['name' => 'Moderator']);
        $mail = $notification->toMail($user);

        $this->assertEquals('Content Flagged for Review - SIBAKA Portal', $mail->subject);
        $allLines = implode(' ', $mail->introLines);
        $this->assertStringContainsString('My Post', $allLines);
        $this->assertStringContainsString('harassment', $allLines);
    }
}
