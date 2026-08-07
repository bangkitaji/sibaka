<?php

namespace Tests\Feature;

use App\Enums\ModerationAction;
use App\Enums\UserRole;
use App\Models\ModerationLog;
use App\Models\User;
use App\Policies\ModerationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Tests\TestCase;

class ModerationLogImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    // --- Immutability Tests ---

    public function testModerationLogCannotBeUpdatedAtEloquentLevel(): void
    {
        $moderator = User::factory()->moderator()->create();
        $log = ModerationLog::create([
            'moderator_id' => $moderator->id,
            'target_user_id' => null,
            'target_content_id' => null,
            'action' => ModerationAction::Dismiss,
            'reason' => 'Test reason',
            'created_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('ModerationLog records are immutable and cannot be updated.');

        $log->update(['reason' => 'Modified reason']);
    }

    public function testModerationLogCannotBeDeletedAtEloquentLevel(): void
    {
        $moderator = User::factory()->moderator()->create();
        $log = ModerationLog::create([
            'moderator_id' => $moderator->id,
            'target_user_id' => null,
            'target_content_id' => null,
            'action' => ModerationAction::Dismiss,
            'reason' => 'Test reason',
            'created_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('ModerationLog records are immutable and cannot be deleted.');

        $log->delete();
    }

    public function testModerationLogCanBeCreated(): void
    {
        $moderator = User::factory()->moderator()->create();

        $log = ModerationLog::create([
            'moderator_id' => $moderator->id,
            'target_user_id' => null,
            'target_content_id' => null,
            'action' => ModerationAction::RemoveContent,
            'reason' => 'Content violated community guidelines',
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('moderation_logs', [
            'id' => $log->id,
            'moderator_id' => $moderator->id,
            'action' => ModerationAction::RemoveContent->value,
            'reason' => 'Content violated community guidelines',
        ]);
    }

    // --- Access Control Tests ---

    public function testOnlyAdminCanViewModerationLogs(): void
    {
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->moderator()->create();
        $member = User::factory()->member()->create();
        $pending = User::factory()->pending()->create();

        $policy = new ModerationPolicy();

        $this->assertTrue($policy->viewModerationLogs($admin));
        $this->assertFalse($policy->viewModerationLogs($moderator));
        $this->assertFalse($policy->viewModerationLogs($member));
        $this->assertFalse($policy->viewModerationLogs($pending));
    }

    public function testViewModerationLogsGateGrantsAccessToAdminOnly(): void
    {
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->moderator()->create();
        $member = User::factory()->member()->create();

        $this->actingAs($admin);
        $this->assertTrue(Gate::allows('view-moderation-logs'));

        $this->actingAs($moderator);
        $this->assertFalse(Gate::allows('view-moderation-logs'));

        $this->actingAs($member);
        $this->assertFalse(Gate::allows('view-moderation-logs'));
    }

    // --- Log Entry Creation Tests ---

    public function testModerationLogRecordsAllRequiredFields(): void
    {
        $moderator = User::factory()->moderator()->create();
        $targetUser = User::factory()->member()->create();

        $log = ModerationLog::create([
            'moderator_id' => $moderator->id,
            'target_user_id' => $targetUser->id,
            'target_content_id' => null,
            'action' => ModerationAction::SuspendUser,
            'reason' => 'Repeated policy violations',
            'created_at' => now(),
        ]);

        $this->assertNotNull($log->id);
        $this->assertEquals($moderator->id, $log->moderator_id);
        $this->assertEquals($targetUser->id, $log->target_user_id);
        $this->assertEquals(ModerationAction::SuspendUser, $log->action);
        $this->assertEquals('Repeated policy violations', $log->reason);
        $this->assertNotNull($log->created_at);
    }

    public function testModerationLogSupportsAllActionTypes(): void
    {
        $moderator = User::factory()->moderator()->create();

        foreach (ModerationAction::cases() as $action) {
            $log = ModerationLog::create([
                'moderator_id' => $moderator->id,
                'target_user_id' => null,
                'target_content_id' => null,
                'action' => $action,
                'reason' => "Testing action: {$action->value}",
                'created_at' => now(),
            ]);

            $this->assertDatabaseHas('moderation_logs', [
                'id' => $log->id,
                'action' => $action->value,
            ]);
        }
    }

    public function testModerationLogRelationships(): void
    {
        $moderator = User::factory()->moderator()->create();
        $log = ModerationLog::create([
            'moderator_id' => $moderator->id,
            'target_user_id' => null,
            'target_content_id' => null,
            'action' => ModerationAction::Dismiss,
            'reason' => 'Test relationships',
            'created_at' => now(),
        ]);

        $this->assertEquals($moderator->id, $log->moderator->id);
    }
}
