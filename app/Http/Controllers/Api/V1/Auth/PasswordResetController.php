<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class PasswordResetController extends Controller
{
    private const GENERIC_RESET_MESSAGE = 'If the account exists, a password reset link has been sent.';

    public function __construct(
        private readonly AuthService $authService,
    ) {}

    #[OA\Post(
        path: '/auth/forgot-password',
        summary: 'Request a password reset link',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Generic success response'),
            new OA\Response(response: 422, description: 'Validation failed'),
            new OA\Response(response: 429, description: 'Too many requests'),
        ],
    )]
    public function sendResetLink(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->requestPasswordReset($request->validated('email'));

        return $this->successResponse(null, self::GENERIC_RESET_MESSAGE);
    }

    #[OA\Post(
        path: '/auth/reset-password',
        summary: 'Reset password using token',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'token', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'token', type: 'string'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password reset successful'),
            new OA\Response(response: 422, description: 'Invalid token or validation failed'),
            new OA\Response(response: 429, description: 'Too many requests'),
        ],
    )]
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        if (! $this->authService->resetPassword($request->validated())) {
            return $this->validationErrorResponse([
                'email' => ['The password reset token is invalid or has expired.'],
            ]);
        }

        return $this->successResponse(null, 'Password reset successful.');
    }
}
