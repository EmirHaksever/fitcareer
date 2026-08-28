<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Controller;
use App\Services\Candidate\CandidateDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    public function __construct(
        private readonly CandidateDashboardService $dashboardService,
    ) {}

    #[OA\Get(
        path: '/candidate/dashboard',
        summary: 'Candidate dashboard aggregates',
        security: [['sanctum' => []]],
        tags: ['Candidate Dashboard'],
        responses: [
            new OA\Response(response: 200, description: 'Dashboard data returned'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function show(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->dashboardService->buildForUser($request->user()),
            'Dashboard retrieved.',
        );
    }
}
