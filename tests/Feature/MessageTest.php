<?php

namespace Tests\Feature;

use App\Models\PortalMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    private User $sender;
    private User $recipient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sender = User::factory()->member()->create();
        $this->recipient = User::factory()->member()->create();
    }

    public function testSendMessageSuccessfully(): void
    {
        $response = $this->actingAs($this->sender)
            ->postJson('/messages', [
                'recipient_id' => $this->recipient->id,
                'body' => 'Hello, I would like to connect!',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Message sent successfully.',
            ]);

        $this->assertDatabaseHas('portal_messages', [
            'sender_id' => $this->sender->id,
            'recipient_id' => $this->recipient->id,
            'body' => 'Hello, I would like to connect!',
            'is_read' => false,
        ]);
    }

    public function testSendMessageBodyMaxLength(): void
    {
        $response = $this->actingAs($this->sender)
            ->postJson('/messages', [
                'recipient_id' => $this->recipient->id,
                'body' => str_repeat('a', 1001),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    public function testSendMessageBodyExactlyMaxLength(): void
    {
        $response = $this->actingAs($this->sender)
            ->postJson('/messages', [
                'recipient_id' => $this->recipient->id,
                'body' => str_repeat('a', 1000),
            ]);

        $response->assertStatus(201);
    }

    public function testSendMessageRequiresBody(): void
    {
        $response = $this->actingAs($this->sender)
            ->postJson('/messages', [
                'recipient_id' => $this->recipient->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    public function testSendMessageRequiresRecipient(): void
    {
        $response = $this->actingAs($this->sender)
            ->postJson('/messages', [
                'body' => 'Hello!',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient_id']);
    }

    public function testSendMessageRejectsInvalidRecipient(): void
    {
        $response = $this->actingAs($this->sender)
            ->postJson('/messages', [
                'recipient_id' => '00000000-0000-0000-0000-000000000000',
                'body' => 'Hello!',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient_id']);
    }

    public function testSendMessageRejectsSelfMessage(): void
    {
        $response = $this->actingAs($this->sender)
            ->postJson('/messages', [
                'recipient_id' => $this->sender->id,
                'body' => 'Message to myself',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'You cannot send a message to yourself.',
            ]);
    }

    public function testSendMessageRateLimitAt10PerDay(): void
    {
        // Create 10 messages already sent today
        PortalMessage::factory()->count(10)->create([
            'sender_id' => $this->sender->id,
            'recipient_id' => $this->recipient->id,
            'created_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($this->sender)
            ->postJson('/messages', [
                'recipient_id' => $this->recipient->id,
                'body' => 'This should be rate limited',
            ]);

        $response->assertStatus(429)
            ->assertJson([
                'message' => 'You have reached the maximum of 10 messages per day.',
            ]);
    }

    public function testSendMessageAllowsAfterRateLimitExpires(): void
    {
        // Create 10 messages sent more than 24 hours ago
        PortalMessage::factory()->count(10)->create([
            'sender_id' => $this->sender->id,
            'recipient_id' => $this->recipient->id,
            'created_at' => now()->subHours(25),
        ]);

        $response = $this->actingAs($this->sender)
            ->postJson('/messages', [
                'recipient_id' => $this->recipient->id,
                'body' => 'This should be allowed after 24h',
            ]);

        $response->assertStatus(201);
    }

    public function testSendMessageRequiresAuthentication(): void
    {
        $response = $this->postJson('/messages', [
            'recipient_id' => $this->recipient->id,
            'body' => 'Hello!',
        ]);

        $response->assertUnauthorized();
    }

    public function testListMessagesForRecipient(): void
    {
        PortalMessage::factory()->count(3)->create([
            'sender_id' => $this->sender->id,
            'recipient_id' => $this->recipient->id,
        ]);

        $response = $this->actingAs($this->recipient)
            ->getJson('/messages');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function testListMessagesDoesNotShowSentMessages(): void
    {
        // Messages sent by the user (should not appear in their inbox)
        PortalMessage::factory()->count(2)->create([
            'sender_id' => $this->sender->id,
            'recipient_id' => $this->recipient->id,
        ]);

        $response = $this->actingAs($this->sender)
            ->getJson('/messages');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testMarkMessageAsRead(): void
    {
        $message = PortalMessage::factory()->unread()->create([
            'sender_id' => $this->sender->id,
            'recipient_id' => $this->recipient->id,
        ]);

        $response = $this->actingAs($this->recipient)
            ->patchJson("/messages/{$message->id}/read");

        $response->assertOk()
            ->assertJson([
                'message' => 'Message marked as read.',
            ]);

        $this->assertDatabaseHas('portal_messages', [
            'id' => $message->id,
            'is_read' => true,
        ]);
    }

    public function testMarkMessageAsUnread(): void
    {
        $message = PortalMessage::factory()->read()->create([
            'sender_id' => $this->sender->id,
            'recipient_id' => $this->recipient->id,
        ]);

        $response = $this->actingAs($this->recipient)
            ->patchJson("/messages/{$message->id}/unread");

        $response->assertOk()
            ->assertJson([
                'message' => 'Message marked as unread.',
            ]);

        $this->assertDatabaseHas('portal_messages', [
            'id' => $message->id,
            'is_read' => false,
        ]);
    }

    public function testOnlyRecipientCanMarkAsRead(): void
    {
        $message = PortalMessage::factory()->unread()->create([
            'sender_id' => $this->sender->id,
            'recipient_id' => $this->recipient->id,
        ]);

        // Sender trying to mark as read should be forbidden
        $response = $this->actingAs($this->sender)
            ->patchJson("/messages/{$message->id}/read");

        $response->assertForbidden();
    }

    public function testOnlyRecipientCanMarkAsUnread(): void
    {
        $message = PortalMessage::factory()->read()->create([
            'sender_id' => $this->sender->id,
            'recipient_id' => $this->recipient->id,
        ]);

        // Sender trying to mark as unread should be forbidden
        $response = $this->actingAs($this->sender)
            ->patchJson("/messages/{$message->id}/unread");

        $response->assertForbidden();
    }
}
