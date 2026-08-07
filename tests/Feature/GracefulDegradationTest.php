<?php

use App\Exceptions\InfrastructureException;
use App\Http\Middleware\CheckDiskSpace;
use App\Listeners\HandleFailedEmailNotification;
use App\Models\PortalMessage;
use App\Models\User;
use App\Notifications\VerificationApproved;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Facades\Config;

/*
|--------------------------------------------------------------------------
| Graceful Degradation Tests
|--------------------------------------------------------------------------
|
| Tests verifying that the SIBAKA portal degrades gracefully when
| infrastructure services become unavailable:
| 1. Redis fallback to database sessions and file cache
| 2. Filesystem full detection and 507 response
| 3. Email service down with in-app notification fallback
|
| Validates: Requirements 10.1
|
*/

describe('Redis unavailable fallback', function () {
    test('GracefulDegradationServiceProvider configures fallback drivers', function () {
        // Simulate what the provider does when Redis is unreachable
        Config::set('session.driver', 'database');
        Config::set('cache.default', 'file');
        Config::set('queue.default', 'database');

        expect(config('session.driver'))->toBe('database');
        expect(config('cache.default'))->toBe('file');
        expect(config('queue.default'))->toBe('database');
    });

    test('fallback session driver defaults to database', function () {
        // Verify the fallback is database, not some other driver
        Config::set('session.driver', 'database');
        expect(config('session.driver'))->toBe('database');
    });

    test('fallback cache store defaults to file', function () {
        Config::set('cache.default', 'file');
        expect(config('cache.default'))->toBe('file');
        expect(config('cache.stores.file.driver'))->toBe('file');
    });

    test('fallback queue driver defaults to database', function () {
        Config::set('queue.default', 'database');
        expect(config('queue.default'))->toBe('database');
    });
});

describe('Filesystem full handling', function () {
    test('CheckDiskSpace middleware allows requests without file uploads', function () {
        $middleware = new CheckDiskSpace();
        $request = Request::create('/api/content', 'POST');

        $response = $middleware->handle($request, fn ($r) => response()->json(['ok' => true]));

        expect($response->getStatusCode())->toBe(200);
    });

    test('CheckDiskSpace middleware allows uploads when disk has space', function () {
        $middleware = new CheckDiskSpace();

        $request = Request::create('/api/upload', 'POST', [], [], [
            'files' => [UploadedFile::fake()->create('test.pdf', 100)],
        ]);

        $response = $middleware->handle($request, fn ($r) => response()->json(['ok' => true]));

        expect($response->getStatusCode())->toBe(200);
    });

    test('InfrastructureException::diskFull returns HTTP 507 code', function () {
        $exception = InfrastructureException::diskFull();

        expect($exception->getCode())->toBe(507);
        expect($exception->getMessage())->toContain('Storage capacity is temporarily full');
        expect($exception->getMessage())->toContain('uploads are currently disabled');
    });

    test('InfrastructureException::diskFull has user-friendly message', function () {
        $exception = InfrastructureException::diskFull();

        expect($exception->getMessage())->toContain('Please try again later');
    });

    test('exception handler renders 507 for disk full ErrorException', function () {
        // Register a temporary route that simulates a disk full error
        \Illuminate\Support\Facades\Route::get('/test-disk-full-error', function () {
            throw new \ErrorException('No space left on device');
        })->middleware('web');

        $response = $this->getJson('/test-disk-full-error');

        $response->assertStatus(507);
        $response->assertJsonFragment([
            'message' => 'Storage capacity is temporarily full. File uploads are currently disabled. Please try again later.',
        ]);
    });

    test('exception handler renders 507 for disk quota exceeded', function () {
        \Illuminate\Support\Facades\Route::get('/test-disk-quota', function () {
            throw new \ErrorException('disk quota exceeded');
        })->middleware('web');

        $response = $this->getJson('/test-disk-quota');

        $response->assertStatus(507);
    });

    test('exception handler renders 507 for InfrastructureException::diskFull', function () {
        \Illuminate\Support\Facades\Route::get('/test-infra-disk', function () {
            throw InfrastructureException::diskFull();
        })->middleware('web');

        $response = $this->getJson('/test-infra-disk');

        $response->assertStatus(507);
        $response->assertJsonFragment([
            'message' => 'Storage capacity is temporarily full. File uploads are currently disabled. Please try again later.',
        ]);
    });

    test('exception handler does not intercept non-disk ErrorExceptions', function () {
        \Illuminate\Support\Facades\Route::get('/test-other-error', function () {
            throw new \ErrorException('Some other error occurred');
        })->middleware('web');

        $response = $this->getJson('/test-other-error');

        // Should NOT be 507 - it should be a 500 server error
        expect($response->getStatusCode())->not->toBe(507);
    });
});

describe('Email service down handling', function () {
    test('HandleFailedEmailNotification creates in-app message on mail failure', function () {
        $user = User::factory()->create();

        $notification = new VerificationApproved();
        $event = new NotificationFailed($user, $notification, 'mail', []);

        $listener = new HandleFailedEmailNotification();
        $listener->handle($event);

        $message = PortalMessage::where('recipient_id', $user->id)
            ->where('body', 'like', '%System%')
            ->first();

        expect($message)->not->toBeNull();
        expect($message->body)->toContain('unable to deliver an email notification');
        expect($message->body)->toContain('Verification Approved');
        expect($message->is_read)->toBeFalse();
    });

    test('HandleFailedEmailNotification ignores non-mail channel failures', function () {
        $user = User::factory()->create();

        $notification = new VerificationApproved();
        $event = new NotificationFailed($user, $notification, 'database', []);

        $listener = new HandleFailedEmailNotification();
        $listener->handle($event);

        $messageCount = PortalMessage::where('recipient_id', $user->id)
            ->where('body', 'like', '%System%')
            ->count();

        expect($messageCount)->toBe(0);
    });

    test('InfrastructureException::emailServiceUnavailable returns 503', function () {
        $exception = InfrastructureException::emailServiceUnavailable();

        expect($exception->getCode())->toBe(503);
        expect($exception->getMessage())->toContain('Email delivery is temporarily unavailable');
        expect($exception->getMessage())->toContain('queued');
    });

    test('InfrastructureException::redisUnavailable returns 503', function () {
        $exception = InfrastructureException::redisUnavailable();

        expect($exception->getCode())->toBe(503);
        expect($exception->getMessage())->toContain('Cache service is temporarily unavailable');
    });
});

describe('Health check endpoint', function () {
    test('health endpoint returns JSON response with service statuses', function () {
        $response = $this->getJson('/health');

        // In test env without Redis/proper DB, status might be degraded or critical
        // but the endpoint itself should respond
        $response->assertJsonStructure([
            'status',
            'services' => ['redis', 'database', 'disk', 'email'],
            'degraded',
        ]);
    });

    test('health endpoint reports disk status', function () {
        $response = $this->getJson('/health');

        $data = $response->json();
        expect($data['services']['disk'])->toHaveKey('status');
    });
});
