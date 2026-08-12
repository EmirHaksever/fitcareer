<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class OptionalSanctumAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() !== null) {
            $accessToken = PersonalAccessToken::findToken($request->bearerToken());

            if ($accessToken !== null && ! $accessToken->expires_at?->isPast()) {
                $request->setUserResolver(static fn () => $accessToken->tokenable);
            }
        }

        return $next($request);
    }
}
