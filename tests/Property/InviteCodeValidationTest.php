<?php

namespace Tests\Property;

use App\Models\InviteCode;
use App\Models\User;
use App\Services\AuthService;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property 2: Invite Code Validation
 *
 * Tests that invite codes are accepted iff they exist, are unused, and unexpired.
 *
 * **Validates: Requirements 1.5**
 */
class InviteCodeValidationTest extends TestCase
{
    use TestTrait;
    use RefreshDatabase;

    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = app(AuthService::class);
    }

    /**
     * Property: A valid invite code (exists, unused, unexpired) allows verification
     * and the user becomes a member with approved status.
     */
    public function testValidInviteCodeVerificationSucceeds(): void
    {
        $this->forAll(
            Generators::string(),
            Generators::choose(1, 30)
        )
            ->withMaxSize(20)
            ->then(function (string $randomSuffix, int $daysUntilExpiry) {
                $generator = User::factory()->member()->create();
                $user = User::factory()->pending()->create();

                $code = 'VALID' . bin2hex(random_bytes(4)) . substr(md5($randomSuffix), 0, 4);

                $inviteCode = InviteCode::create([
                    'generated_by' => $generator->id,
                    'code' => $code,
                    'is_used' => false,
                    'used_by' => null,
                    'expires_at' => now()->addDays($daysUntilExpiry),
                ]);

                $this->authService->verifyWithInviteCode($user->id, $code);

                $user->refresh();
                $inviteCode->refresh();

                $this->assertEquals('member', $user->role->value ?? $user->role);
                $this->assertEquals('approved', $user->verification_status->value ?? $user->verification_status);
                $this->assertTrue($inviteCode->is_used);
                $this->assertEquals($user->id, $inviteCode->used_by);
            });
    }

    /**
     * Property: A used invite code always causes verification to fail.
     */
    public function testUsedInviteCodeVerificationFails(): void
    {
        $this->forAll(
            Generators::string(),
            Generators::choose(1, 30)
        )
            ->withMaxSize(20)
            ->then(function (string $randomSuffix, int $daysUntilExpiry) {
                $generator = User::factory()->member()->create();
                $previousUser = User::factory()->member()->create();
                $user = User::factory()->pending()->create();

                $code = 'USED' . bin2hex(random_bytes(4)) . substr(md5($randomSuffix), 0, 4);

                InviteCode::create([
                    'generated_by' => $generator->id,
                    'code' => $code,
                    'is_used' => true,
                    'used_by' => $previousUser->id,
                    'expires_at' => now()->addDays($daysUntilExpiry),
                ]);

                $threw = false;
                try {
                    $this->authService->verifyWithInviteCode($user->id, $code);
                } catch (ModelNotFoundException $e) {
                    $threw = true;
                }

                $this->assertTrue($threw, 'Used invite code should throw ModelNotFoundException');

                $user->refresh();
                $this->assertEquals('pending', $user->role->value ?? $user->role);
            });
    }

    /**
     * Property: An expired invite code always causes verification to fail.
     */
    public function testExpiredInviteCodeVerificationFails(): void
    {
        $this->forAll(
            Generators::string(),
            Generators::choose(1, 365)
        )
            ->withMaxSize(20)
            ->then(function (string $randomSuffix, int $daysExpiredAgo) {
                $generator = User::factory()->member()->create();
                $user = User::factory()->pending()->create();

                $code = 'EXPD' . bin2hex(random_bytes(4)) . substr(md5($randomSuffix), 0, 4);

                InviteCode::create([
                    'generated_by' => $generator->id,
                    'code' => $code,
                    'is_used' => false,
                    'used_by' => null,
                    'expires_at' => now()->subDays($daysExpiredAgo),
                ]);

                $threw = false;
                try {
                    $this->authService->verifyWithInviteCode($user->id, $code);
                } catch (ModelNotFoundException $e) {
                    $threw = true;
                }

                $this->assertTrue($threw, 'Expired invite code should throw ModelNotFoundException');

                $user->refresh();
                $this->assertEquals('pending', $user->role->value ?? $user->role);
            });
    }

    /**
     * Property: A non-existent code always causes verification to fail.
     */
    public function testNonExistentCodeVerificationFails(): void
    {
        $this->forAll(
            Generators::string()
        )
            ->withMaxSize(20)
            ->then(function (string $randomCode) {
                $user = User::factory()->pending()->create();

                $code = 'NOEX' . bin2hex(random_bytes(8)) . substr(md5($randomCode), 0, 6);

                $threw = false;
                try {
                    $this->authService->verifyWithInviteCode($user->id, $code);
                } catch (ModelNotFoundException $e) {
                    $threw = true;
                }

                $this->assertTrue($threw, 'Non-existent code should throw ModelNotFoundException');

                $user->refresh();
                $this->assertEquals('pending', $user->role->value ?? $user->role);
            });
    }

    /**
     * Property: After successful use, the invite code is marked as used with the correct user_id.
     */
    public function testSuccessfulUseMarksCodeAsUsed(): void
    {
        $this->forAll(
            Generators::string(),
            Generators::choose(1, 30)
        )
            ->withMaxSize(20)
            ->then(function (string $randomSuffix, int $daysUntilExpiry) {
                $generator = User::factory()->member()->create();
                $user = User::factory()->pending()->create();

                $code = 'MARK' . bin2hex(random_bytes(4)) . substr(md5($randomSuffix), 0, 4);

                $inviteCode = InviteCode::create([
                    'generated_by' => $generator->id,
                    'code' => $code,
                    'is_used' => false,
                    'used_by' => null,
                    'expires_at' => now()->addDays($daysUntilExpiry),
                ]);

                $this->authService->verifyWithInviteCode($user->id, $code);

                $inviteCode->refresh();

                $this->assertTrue($inviteCode->is_used);
                $this->assertEquals($user->id, $inviteCode->used_by);

                // Attempting to use the same code again should now fail
                $anotherUser = User::factory()->pending()->create();
                $threw = false;
                try {
                    $this->authService->verifyWithInviteCode($anotherUser->id, $code);
                } catch (ModelNotFoundException $e) {
                    $threw = true;
                }

                $this->assertTrue($threw, 'Code should not be reusable after being used');
            });
    }
}
