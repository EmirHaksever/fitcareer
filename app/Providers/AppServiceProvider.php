<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\RateLimitIdentity;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('job-search', function (Request $request): Limit {
            return Limit::perMinute(config('rate_limits.job_search.per_user_per_minute'))
                ->by(RateLimitIdentity::jobSearch($request));
        });

        RateLimiter::for('job-refresh', function (Request $request): Limit {
            return Limit::perMinute(config('rate_limits.job_refresh.per_user_per_minute'))
                ->by(RateLimitIdentity::authenticatedUserId($request));
        });

        RateLimiter::for('auth-login', function (Request $request): array {
            return [
                Limit::perMinute(config('rate_limits.auth.login_per_minute_by_ip'))
                    ->by(RateLimitIdentity::ip($request)),
                Limit::perMinute(config('rate_limits.auth.login_per_minute_by_email'))
                    ->by(RateLimitIdentity::normalizedEmailKey($request, 'login')),
            ];
        });

        RateLimiter::for('auth-password-reset', function (Request $request): array {
            return [
                Limit::perMinute(config('rate_limits.auth.password_reset_per_minute_by_ip'))
                    ->by(RateLimitIdentity::ip($request)),
                Limit::perMinute(config('rate_limits.auth.password_reset_per_minute_by_email'))
                    ->by(RateLimitIdentity::normalizedEmailKey($request, 'password-reset')),
            ];
        });

        RateLimiter::for('auth-register', function (Request $request): Limit {
            return Limit::perMinute(config('rate_limits.auth.register_per_minute'))
                ->by(RateLimitIdentity::ip($request));
        });

        RateLimiter::for('ai-trigger', function (Request $request): Limit {
            return Limit::perMinute(config('rate_limits.ai_trigger.per_user_per_minute'))
                ->by(RateLimitIdentity::authenticatedUserId($request));
        });

        RateLimiter::for('job-report', function (Request $request): Limit {
            return Limit::perMinute(config('rate_limits.job_report.per_user_per_minute'))
                ->by(RateLimitIdentity::authenticatedUserId($request));
        });
    }
}
