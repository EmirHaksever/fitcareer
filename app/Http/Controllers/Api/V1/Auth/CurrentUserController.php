<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CurrentUserController extends Controller
{
    #[OA\Get(
        path: '/auth/me',
        summary: 'Get the authenticated user profile',
        security: [['sanctum' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Authenticated user returned'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ],
    )]
    public function show(Request $request): JsonResponse
    {
        return $this->successResponse(
            new UserResource($request->user()),
            'Authenticated user retrieved.',
        );
    }
}
