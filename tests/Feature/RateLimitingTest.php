<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\RateLimitIdentity;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'rate_limits.job_search.per_user_per_minute' => 2,
            'rate_limits.job_refresh.per_user_per_minute' => 2,
            'rate_limits.auth.login_per_minute_by_ip' => 2,
            'rate_limits.auth.login_per_minute_by_email' => 2,
            'rate_limits.auth.password_reset_per_minute_by_ip' => 2,
            'rate_limits.auth.password_reset_per_minute_by_email' => 2,
            'rate_limits.auth.register_per_minute' => 2,
        ]);
    }

    #[Test]
    public function authenticated_user_search_limit_uses_user_id_identity(): void
    {
        $user = new User;
        $user->id = 101;
        $user->exists = true;

        $request = $this->makeRequest(ip: '203.0.113.10', user: $user);
        $limit = $this->resolveSingleLimit('job-search', $request);

        $this->assertSame(101, $limit->key);

        $this->hitResolvedLimit('job-search', $request, 2);
        $this->assertTrue($this->isResolvedLimitExceeded('job-search', $request));
    }

    #[Test]
    public function guest_search_limit_uses_ip_identity(): void
    {
        $request = $this->makeRequest(ip: '203.0.113.20');
        $limit = $this->resolveSingleLimit('job-search', $request);

        $this->assertSame('203.0.113.20', $limit->key);

        $this->hitResolvedLimit('job-search', $request, 2);
        $this->assertTrue($this->isResolvedLimitExceeded('job-search', $request));
    }

    #[Test]
    public function job_refresh_limit_uses_authenticated_user_id(): void
    {
        $user = new User;
        $user->id = 55;
        $user->exists = true;

        $request = $this->makeRequest(ip: '203.0.113.30', user: $user);
        $limit = $this->resolveSingleLimit('job-refresh', $request);

        $this->assertSame(55, $limit->key);

        $this->hitResolvedLimit('job-refresh', $request, 2);
        $this->assertTrue($this->isResolvedLimitExceeded('job-refresh', $request));
    }

    #[Test]
    public function login_same_ip_with_different_emails_is_limited_by_ip_bucket(): void
    {
        $ip = '203.0.113.40';

        $firstEmailRequest = $this->makeRequest(ip: $ip, payload: ['email' => 'first@example.com']);
        $secondEmailRequest = $this->makeRequest(ip: $ip, payload: ['email' => 'second@example.com']);
        $thirdEmailRequest = $this->makeRequest(ip: $ip, payload: ['email' => 'third@example.com']);

        $this->hitResolvedLimit('auth-login', $firstEmailRequest, 1, onlyKey: RateLimitIdentity::ip($firstEmailRequest));
        $this->hitResolvedLimit('auth-login', $secondEmailRequest, 1, onlyKey: RateLimitIdentity::ip($secondEmailRequest));

        $this->assertTrue($this->isResolvedLimitExceeded('auth-login', $thirdEmailRequest, onlyKey: RateLimitIdentity::ip($thirdEmailRequest)));
        $this->assertFalse($this->isResolvedLimitExceeded('auth-login', $thirdEmailRequest, onlyKey: RateLimitIdentity::normalizedEmailKey($thirdEmailRequest, 'login')));
    }

    #[Test]
    public function login_different_ips_with_same_normalized_email_share_email_bucket(): void
    {
        $emailLimits = $this->resolveLimits('auth-login', $this->makeRequest(
            ip: '203.0.113.51',
            payload: ['email' => 'User@Example.COM'],
        ));

        $emailKey = RateLimitIdentity::normalizedEmailKey(
            $this->makeRequest(ip: '203.0.113.52', payload: ['email' => 'user@example.com']),
            'login',
        );

        $this->assertSame($emailKey, $emailLimits[1]->key);
        $this->assertStringStartsWith('login:', $emailKey);
        $this->assertStringNotContainsString('user@example.com', $emailKey);

        $firstIpRequest = $this->makeRequest(ip: '203.0.113.61', payload: ['email' => 'target@example.com']);
        $secondIpRequest = $this->makeRequest(ip: '203.0.113.62', payload: ['email' => 'TARGET@example.com']);

        $this->hitResolvedLimit('auth-login', $firstIpRequest, 1, onlyKey: RateLimitIdentity::normalizedEmailKey($firstIpRequest, 'login'));
        $this->hitResolvedLimit('auth-login', $secondIpRequest, 1, onlyKey: RateLimitIdentity::normalizedEmailKey($secondIpRequest, 'login'));

        $thirdIpRequest = $this->makeRequest(ip: '203.0.113.63', payload: ['email' => 'target@example.com']);
        $this->assertTrue($this->isResolvedLimitExceeded('auth-login', $thirdIpRequest, onlyKey: RateLimitIdentity::normalizedEmailKey($thirdIpRequest, 'login')));
        $this->assertFalse($this->isResolvedLimitExceeded('auth-login', $thirdIpRequest, onlyKey: RateLimitIdentity::ip($thirdIpRequest)));
    }

    #[Test]
    public function password_reset_limits_apply_ip_and_normalized_email_independently(): void
    {
        $ip = '203.0.113.70';

        $firstRequest = $this->makeRequest(ip: $ip, payload: ['email' => 'one@example.com']);
        $secondRequest = $this->makeRequest(ip: $ip, payload: ['email' => 'two@example.com']);
        $thirdRequest = $this->makeRequest(ip: $ip, payload: ['email' => 'three@example.com']);

        $this->hitResolvedLimit('auth-password-reset', $firstRequest, 1, onlyKey: RateLimitIdentity::ip($firstRequest));
        $this->hitResolvedLimit('auth-password-reset', $secondRequest, 1, onlyKey: RateLimitIdentity::ip($secondRequest));
        $this->assertTrue($this->isResolvedLimitExceeded('auth-password-reset', $thirdRequest, onlyKey: RateLimitIdentity::ip($thirdRequest)));

        $distributedFirst = $this->makeRequest(ip: '203.0.113.71', payload: ['email' => 'shared@example.com']);
        $distributedSecond = $this->makeRequest(ip: '203.0.113.72', payload: ['email' => 'SHARED@example.com']);

        $this->hitResolvedLimit('auth-password-reset', $distributedFirst, 1, onlyKey: RateLimitIdentity::normalizedEmailKey($distributedFirst, 'password-reset'));
        $this->hitResolvedLimit('auth-password-reset', $distributedSecond, 1, onlyKey: RateLimitIdentity::normalizedEmailKey($distributedSecond, 'password-reset'));

        $distributedThird = $this->makeRequest(ip: '203.0.113.73', payload: ['email' => 'shared@example.com']);
        $this->assertTrue($this->isResolvedLimitExceeded(
            'auth-password-reset',
            $distributedThird,
            onlyKey: RateLimitIdentity::normalizedEmailKey($distributedThird, 'password-reset'),
        ));
    }

    #[Test]
    public function register_limit_uses_ip_identity(): void
    {
        $request = $this->makeRequest(ip: '203.0.113.80', payload: ['email' => 'new@example.com']);
        $limit = $this->resolveSingleLimit('auth-register', $request);

        $this->assertSame('203.0.113.80', $limit->key);

        $this->hitResolvedLimit('auth-register', $request, 2);
        $this->assertTrue($this->isResolvedLimitExceeded('auth-register', $request));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function makeRequest(string $ip, array $payload = [], ?User $user = null): Request
    {
        $request = Request::create('/', 'POST', $payload, [], [], ['REMOTE_ADDR' => $ip]);

        if ($user !== null) {
            $request->setUserResolver(static fn () => $user);
        }

        return $request;
    }

    private function resolveSingleLimit(string $limiterName, Request $request): Limit
    {
        $limits = $this->resolveLimits($limiterName, $request);

        $this->assertCount(1, $limits);

        return $limits[0];
    }

    /**
     * @return array<int, Limit>
     */
    private function resolveLimits(string $limiterName, Request $request): array
    {
        $limiter = RateLimiter::limiter($limiterName);
        $this->assertNotNull($limiter);

        $limits = $limiter($request);

        return is_array($limits) ? $limits : [$limits];
    }

    private function hitResolvedLimit(
        string $limiterName,
        Request $request,
        int $times = 1,
        int|string|null $onlyKey = null,
    ): void {
        foreach ($this->resolveLimits($limiterName, $request) as $limit) {
            if ($onlyKey !== null && $limit->key !== $onlyKey) {
                continue;
            }

            $key = $this->middlewareKey($limiterName, $limit);

            for ($attempt = 0; $attempt < $times; $attempt++) {
                RateLimiter::hit($key, $limit->decaySeconds);
            }
        }
    }

    private function isResolvedLimitExceeded(
        string $limiterName,
        Request $request,
        int|string|null $onlyKey = null,
    ): bool {
        foreach ($this->resolveLimits($limiterName, $request) as $limit) {
            if ($onlyKey !== null && $limit->key !== $onlyKey) {
                continue;
            }

            $key = $this->middlewareKey($limiterName, $limit);

            if (RateLimiter::tooManyAttempts($key, $limit->maxAttempts)) {
                return true;
            }
        }

        return false;
    }

    private function middlewareKey(string $limiterName, Limit $limit): string
    {
        return md5($limiterName.$limit->key);
    }
}
