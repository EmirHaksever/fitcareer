<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Job;

use App\Http\Controllers\Controller;
use App\Services\Job\PublicJobStatsService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class PublicStatsController extends Controller
{
    public function __construct(
        private readonly PublicJobStatsService $statsService,
    ) {}

    #[OA\Get(
        path: '/stats',
        summary: 'Public catalog counts (published jobs, trust-analyzed jobs, active sources)',
        tags: ['Jobs'],
        responses: [
            new OA\Response(response: 200, description: 'Stats returned'),
        ],
    )]
    public function show(): JsonResponse
    {
        return $this->successResponse($this->statsService->get(), 'Stats retrieved.');
    }
}
