<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

class LogoutController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    #[OA\Post(
        path: '/auth/logout',
        summary: 'Revoke the current Sanctum access token',
        security: [['sanctum' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Logged out successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ],
    )]
    public function destroy(Request $request): JsonResponse
    {
        $this->authService->logout($request->user(), $request->bearerToken());

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->successResponse(null, 'Logged out successfully.');
    }
}
