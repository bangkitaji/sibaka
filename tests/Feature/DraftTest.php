<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class DraftTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Content $content;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->member()->create();
        $this->content = Content::factory()->create(['author_id' => $this->user->id]);
    }

    public function testAutoSaveDraftReturnsSuccess(): void
    {
        Redis::shouldReceive('setex')
            ->once()
            ->with('draft:' . $this->content->id, 604800, '<p>Draft content</p>');

        $response = $this->actingAs($this->user)
            ->putJson("/content/{$this->content->id}/draft", [
                'body' => '<p>Draft content</p>',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'saved_at']);
    }

    public function testAutoSaveDraftRequiresBody(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson("/content/{$this->content->id}/draft", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    public function testAutoSaveDraftRejectsUnauthorizedUser(): void
    {
        $otherUser = User::factory()->member()->create();

        $response = $this->actingAs($otherUser)
            ->putJson("/content/{$this->content->id}/draft", [
                'body' => '<p>Unauthorized draft</p>',
            ]);

        $response->assertNotFound();
    }

    public function testAutoSaveDraftRequiresAuthentication(): void
    {
        $response = $this->putJson("/content/{$this->content->id}/draft", [
            'body' => '<p>Anonymous draft</p>',
        ]);

        $response->assertUnauthorized();
    }

    public function testRestoreDraftReturnsBody(): void
    {
        Redis::shouldReceive('get')
            ->once()
            ->with('draft:' . $this->content->id)
            ->andReturn('<p>Saved draft body</p>');

        $response = $this->actingAs($this->user)
            ->getJson("/content/{$this->content->id}/draft");

        $response->assertOk()
            ->assertJson([
                'body' => '<p>Saved draft body</p>',
                'has_draft' => true,
            ]);
    }

    public function testRestoreDraftReturnsNullWhenNoDraft(): void
    {
        Redis::shouldReceive('get')
            ->once()
            ->with('draft:' . $this->content->id)
            ->andReturn(null);

        $response = $this->actingAs($this->user)
            ->getJson("/content/{$this->content->id}/draft");

        $response->assertOk()
            ->assertJson([
                'body' => null,
                'has_draft' => false,
            ]);
    }

    public function testRestoreDraftRejectsUnauthorizedUser(): void
    {
        $otherUser = User::factory()->member()->create();

        $response = $this->actingAs($otherUser)
            ->getJson("/content/{$this->content->id}/draft");

        $response->assertNotFound();
    }
}
