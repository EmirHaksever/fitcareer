<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\AuthTokenResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class RegisterController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    #[OA\Post(
        path: '/auth/register',
        summary: 'Register a new candidate or company account',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation', 'role'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Ada Lovelace'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ada@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                    new OA\Property(property: 'role', type: 'string', enum: ['candidate', 'company']),
                    new OA\Property(property: 'company_name', type: 'string', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Registered successfully'),
            new OA\Response(response: 422, description: 'Validation failed'),
            new OA\Response(response: 429, description: 'Too many requests'),
        ],
    )]
    public function store(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->createdResponse(
            new AuthTokenResource($result),
            'Registration successful.',
        );
    }
}
