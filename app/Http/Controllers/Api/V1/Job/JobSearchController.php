<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Job;

use App\Http\Controllers\Controller;
use App\Http\Requests\Job\JobSearchRequest;
use App\Http\Resources\Job\JobListResource;
use App\Services\Job\JobSearchService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class JobSearchController extends Controller
{
    public function __construct(
        private readonly JobSearchService $jobSearchService,
    ) {}

    #[OA\Get(
        path: '/jobs',
        summary: 'Search published jobs',
        tags: ['Jobs'],
        parameters: [
            new OA\Parameter(name: 'keyword', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'location', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'min_fit_score', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Jobs returned'),
            new OA\Response(response: 422, description: 'Validation failed'),
            new OA\Response(response: 429, description: 'Too many requests'),
        ],
    )]
    public function index(JobSearchRequest $request): JsonResponse
    {
        $query = $request->toQuery();
        $paginator = $this->jobSearchService->search($query);

        return $this->successResponse([
            'items' => collect($paginator->items())
                ->map(fn ($job) => (new JobListResource($job, $query->candidateProfileId))->resolve())
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Jobs retrieved.');
    }
}
