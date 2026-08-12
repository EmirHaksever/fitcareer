<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class EmailVerificationController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    #[OA\Post(
        path: '/auth/email/verification-notification',
        summary: 'Resend email verification notification',
        security: [['sanctum' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Verification notification sent'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ],
    )]
    public function send(Request $request): JsonResponse
    {
        $this->authService->sendEmailVerificationNotification($request->user());

        return $this->successResponse(null, 'Verification notification sent.');
    }

    #[OA\Get(
        path: '/auth/email/verify/{id}/{hash}',
        summary: 'Verify email address',
        tags: ['Auth'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'hash', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Email verified'),
            new OA\Response(response: 403, description: 'Invalid verification link'),
        ],
    )]
    public function verify(Request $request, int $id, string $hash): JsonResponse
    {
        if (! $request->hasValidSignature()) {
            return $this->errorResponse('Invalid verification link.', null, 403);
        }

        $user = User::query()->findOrFail($id);

        if (! $this->authService->verifyEmail($user, $hash)) {
            return $this->errorResponse('Invalid verification link.', null, 403);
        }

        return $this->successResponse(null, 'Email verified successfully.');
    }
}
