<?php

namespace Tests\Property;

use App\Http\Middleware\RateLimitByIp;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Property 24: Rate Limiting
 *
 * *For any* IP address with N requests in the current 1-minute window, the system SHALL
 * serve requests normally if N <= 100 and SHALL present a CAPTCHA challenge if N > 100.
 *
 * **Validates: Requirements 11.4**
 */
class RateLimitPropertyTest extends TestCase
{
    use TestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('ip_rate_limit:192.168.1.1');
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('ip_rate_limit:192.168.1.1');
        parent::tearDown();
    }

    /**
     * Helper: Send a request through the RateLimitByIp middleware.
     */
    private function sendRequestThroughMiddleware(string $ip = '192.168.1.1'): \Symfony\Component\HttpFoundation\Response
    {
        $request = Request::create('/test', 'GET');
        $request->server->set('REMOTE_ADDR', $ip);

        $middleware = new RateLimitByIp();

        return $middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });
    }

    /**
     * Property: For N <= 100 requests from the same IP within 1 minute,
     * all requests return 200 (normal response).
     */
    public function testRequestsWithinLimitAreServedNormally(): void
    {
        $this->forAll(
            Generators::choose(1, 100)
        )
            ->then(function (int $requestCount) {
                // Clear rate limiter state before each iteration
                RateLimiter::clear('ip_rate_limit:192.168.1.1');

                for ($i = 0; $i < $requestCount; $i++) {
                    $response = $this->sendRequestThroughMiddleware();

                    $this->assertEquals(
                        200,
                        $response->getStatusCode(),
                        "Request #{$i} of {$requestCount} should return 200 (within limit)"
                    );
                }
            });
    }

    /**
     * Property: For N > 100 requests from the same IP, request 101+ returns 429
     * with captcha_required=true.
     */
    public function testRequestsExceedingLimitReturnCaptchaChallenge(): void
    {
        $this->forAll(
            Generators::choose(101, 120)
        )
            ->then(function (int $totalRequests) {
                // Clear rate limiter state before each iteration
                RateLimiter::clear('ip_rate_limit:192.168.1.1');

                // First 100 requests should succeed
                for ($i = 0; $i < 100; $i++) {
                    $response = $this->sendRequestThroughMiddleware();
                    $this->assertEquals(
                        200,
                        $response->getStatusCode(),
                        "Request #{$i} should return 200 (within limit)"
                    );
                }

                // Request 101+ should be rate limited
                for ($i = 100; $i < $totalRequests; $i++) {
                    $response = $this->sendRequestThroughMiddleware();

                    $this->assertEquals(
                        429,
                        $response->getStatusCode(),
                        "Request #{$i} (>{$totalRequests}) should return 429 (rate limited)"
                    );

                    $body = json_decode($response->getContent(), true);
                    $this->assertTrue(
                        $body['captcha_required'] ?? false,
                        "Request #{$i} should include captcha_required=true"
                    );
                }
            });
    }

    /**
     * Property: The 101st request is always the first to be rate limited (boundary test).
     * For any random IP suffix, the boundary is exactly at request 101.
     */
    public function testRateLimitBoundaryIsExactlyAtOneHundredOne(): void
    {
        $this->forAll(
            Generators::choose(1, 254) // Random IP last octet
        )
            ->then(function (int $ipSuffix) {
                $ip = "10.0.0.{$ipSuffix}";
                RateLimiter::clear("ip_rate_limit:{$ip}");

                // Send exactly 100 requests - all should succeed
                for ($i = 0; $i < 100; $i++) {
                    $request = Request::create('/test', 'GET');
                    $request->server->set('REMOTE_ADDR', $ip);
                    $middleware = new RateLimitByIp();
                    $response = $middleware->handle($request, function ($req) {
                        return new Response('OK', 200);
                    });
                    $this->assertEquals(200, $response->getStatusCode());
                }

                // 101st request should be rate limited
                $request = Request::create('/test', 'GET');
                $request->server->set('REMOTE_ADDR', $ip);
                $middleware = new RateLimitByIp();
                $response = $middleware->handle($request, function ($req) {
                    return new Response('OK', 200);
                });

                $this->assertEquals(
                    429,
                    $response->getStatusCode(),
                    "Request 101 from IP {$ip} should be rate limited"
                );

                $body = json_decode($response->getContent(), true);
                $this->assertTrue(
                    $body['captcha_required'] ?? false,
                    "Request 101 from IP {$ip} should require CAPTCHA"
                );

                // Clean up
                RateLimiter::clear("ip_rate_limit:{$ip}");
            });
    }

    /**
     * Property: Different IPs have independent rate limits.
     * For any two distinct IPs, exhausting the limit on one does not affect the other.
     */
    public function testDifferentIpsHaveIndependentRateLimits(): void
    {
        $this->forAll(
            Generators::choose(1, 50) // Number of requests for second IP
        )
            ->then(function (int $secondIpRequests) {
                $ip1 = '172.16.0.1';
                $ip2 = '172.16.0.2';
                RateLimiter::clear("ip_rate_limit:{$ip1}");
                RateLimiter::clear("ip_rate_limit:{$ip2}");

                // Exhaust rate limit for IP1 (send 101 requests)
                for ($i = 0; $i < 101; $i++) {
                    $request = Request::create('/test', 'GET');
                    $request->server->set('REMOTE_ADDR', $ip1);
                    $middleware = new RateLimitByIp();
                    $middleware->handle($request, function ($req) {
                        return new Response('OK', 200);
                    });
                }

                // Verify IP1 is rate limited
                $request = Request::create('/test', 'GET');
                $request->server->set('REMOTE_ADDR', $ip1);
                $middleware = new RateLimitByIp();
                $response = $middleware->handle($request, function ($req) {
                    return new Response('OK', 200);
                });
                $this->assertEquals(429, $response->getStatusCode());

                // IP2 should still be served normally
                for ($i = 0; $i < $secondIpRequests; $i++) {
                    $request = Request::create('/test', 'GET');
                    $request->server->set('REMOTE_ADDR', $ip2);
                    $middleware = new RateLimitByIp();
                    $response = $middleware->handle($request, function ($req) {
                        return new Response('OK', 200);
                    });
                    $this->assertEquals(
                        200,
                        $response->getStatusCode(),
                        "IP2 request #{$i} should return 200 (IP1's limit should not affect IP2)"
                    );
                }

                // Clean up
                RateLimiter::clear("ip_rate_limit:{$ip1}");
                RateLimiter::clear("ip_rate_limit:{$ip2}");
            });
    }

    /**
     * Property: Rate limit response includes correct structure.
     * When rate limited, response always contains: captcha_required (true),
     * retry_after (positive int), and correct headers.
     */
    public function testRateLimitResponseStructure(): void
    {
        $this->forAll(
            Generators::choose(101, 110)
        )
            ->then(function (int $totalRequests) {
                $ip = '192.168.100.1';
                RateLimiter::clear("ip_rate_limit:{$ip}");

                // Exhaust the rate limit
                for ($i = 0; $i < 100; $i++) {
                    $request = Request::create('/test', 'GET');
                    $request->server->set('REMOTE_ADDR', $ip);
                    $middleware = new RateLimitByIp();
                    $middleware->handle($request, function ($req) {
                        return new Response('OK', 200);
                    });
                }

                // Verify rate-limited response structure
                $request = Request::create('/test', 'GET');
                $request->server->set('REMOTE_ADDR', $ip);
                $middleware = new RateLimitByIp();
                $response = $middleware->handle($request, function ($req) {
                    return new Response('OK', 200);
                });

                $this->assertEquals(429, $response->getStatusCode());

                $body = json_decode($response->getContent(), true);

                // captcha_required must be true
                $this->assertArrayHasKey('captcha_required', $body);
                $this->assertTrue($body['captcha_required']);

                // retry_after must be a positive integer
                $this->assertArrayHasKey('retry_after', $body);
                $this->assertIsInt($body['retry_after']);
                $this->assertGreaterThan(0, $body['retry_after']);

                // Headers must be present
                $this->assertTrue($response->headers->has('Retry-After'));
                $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
                $this->assertTrue($response->headers->has('X-RateLimit-Remaining'));
                $this->assertEquals('0', $response->headers->get('X-RateLimit-Remaining'));
                $this->assertEquals('100', $response->headers->get('X-RateLimit-Limit'));

                // Clean up
                RateLimiter::clear("ip_rate_limit:{$ip}");
            });
    }
}
