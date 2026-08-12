<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class PasswordUpdateController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    #[OA\Put(
        path: '/auth/password',
        summary: 'Update the authenticated user password',
        security: [['sanctum' => []]],
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password updated'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ],
    )]
    public function update(UpdatePasswordRequest $request): JsonResponse
    {
        $this->authService->updatePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password'),
        );

        return $this->successResponse(null, 'Password updated successfully.');
    }
}
