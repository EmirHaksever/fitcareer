<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthTokenResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class LoginController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    #[OA\Post(
        path: '/auth/login',
        summary: 'Authenticate and issue a Sanctum API token',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login successful'),
            new OA\Response(response: 401, description: 'Invalid credentials'),
            new OA\Response(response: 422, description: 'Validation failed'),
            new OA\Response(response: 429, description: 'Too many requests'),
        ],
    )]
    public function store(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        if ($result === null) {
            return $this->errorResponse('Invalid credentials.', null, 401);
        }

        return $this->successResponse(
            new AuthTokenResource($result),
            'Login successful.',
        );
    }
}
