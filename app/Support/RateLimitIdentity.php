<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class RateLimitIdentity
{
    public static function jobSearch(Request $request): int|string
    {
        return $request->user()?->id ?? $request->ip();
    }

    public static function authenticatedUserId(Request $request): int|string
    {
        return $request->user()->id;
    }

    public static function ip(Request $request): string
    {
        return $request->ip();
    }

    public static function normalizedEmailKey(Request $request, string $context): string
    {
        $email = Str::lower(trim((string) $request->input('email')));

        if ($email === '') {
            return "{$context}:missing-email";
        }

        return "{$context}:".hash('sha256', $email);
    }
}
