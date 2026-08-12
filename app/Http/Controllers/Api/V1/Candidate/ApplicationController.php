<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\ListApplicationsRequest;
use App\Http\Requests\Candidate\StoreApplicationRequest;
use App\Http\Resources\Candidate\ApplicationResource;
use App\Services\Application\ApplicationService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationService $applicationService,
    ) {}

    #[OA\Get(
        path: '/candidate/applications',
        summary: 'List candidate applications',
        security: [['sanctum' => []]],
        tags: ['Candidate Applications'],
        responses: [
            new OA\Response(response: 200, description: 'Applications returned'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function index(ListApplicationsRequest $request): JsonResponse
    {
        $paginator = $this->applicationService->listForUser(
            $request->user(),
            (int) $request->query('page', 1),
            (int) $request->query('per_page', 15),
        );

        return $this->successResponse([
            'items' => ApplicationResource::collection($paginator->items())->resolve(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Applications retrieved.');
    }

    #[OA\Post(
        path: '/candidate/applications',
        summary: 'Submit a job application',
        security: [['sanctum' => []]],
        tags: ['Candidate Applications'],
        responses: [
            new OA\Response(response: 201, description: 'Application created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ],
    )]
    public function store(StoreApplicationRequest $request): JsonResponse
    {
        $application = $this->applicationService->submit(
            $request->user(),
            $request->validated(),
        );

        return $this->createdResponse(
            new ApplicationResource($application),
            'Application submitted.',
        );
    }

    #[OA\Get(
        path: '/candidate/applications/{application}',
        summary: 'Show a candidate application',
        security: [['sanctum' => []]],
        tags: ['Candidate Applications'],
        responses: [
            new OA\Response(response: 200, description: 'Application returned'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ],
    )]
    public function show(int $application): JsonResponse
    {
        $resource = $this->applicationService->getForUser(
            request()->user(),
            $application,
        );

        return $this->successResponse(
            new ApplicationResource($resource),
            'Application retrieved.',
        );
    }
}
