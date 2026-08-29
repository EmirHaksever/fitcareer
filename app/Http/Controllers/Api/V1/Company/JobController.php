<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Job\StoreJobRequest;
use App\Http\Requests\Job\UpdateJobRequest;
use App\Http\Resources\Job\CompanyJobResource;
use App\Models\Job;
use App\Services\Job\JobService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class JobController extends Controller
{
    public function __construct(
        private readonly JobService $jobService,
    ) {}

    #[OA\Get(
        path: '/company/jobs',
        summary: 'List company jobs',
        security: [['sanctum' => []]],
        tags: ['Company Jobs'],
        responses: [
            new OA\Response(response: 200, description: 'Jobs returned'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Job::class);

        $paginator = $this->jobService->listForCompany(
            request()->user(),
            (int) request()->query('page', 1),
            (int) request()->query('per_page', 15),
        );

        return $this->successResponse([
            'items' => CompanyJobResource::collection($paginator->items())->resolve(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Company jobs retrieved.');
    }

    #[OA\Post(
        path: '/company/jobs',
        summary: 'Create internal job draft',
        security: [['sanctum' => []]],
        tags: ['Company Jobs'],
        responses: [
            new OA\Response(response: 201, description: 'Job created'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ],
    )]
    public function store(StoreJobRequest $request): JsonResponse
    {
        $this->authorize('create', Job::class);

        $job = $this->jobService->create(
            $request->user(),
            $request->validated(),
        );

        return $this->createdResponse(
            new CompanyJobResource($job),
            'Job created.',
        );
    }

    #[OA\Get(
        path: '/company/jobs/{job}',
        summary: 'Get company job detail',
        security: [['sanctum' => []]],
        tags: ['Company Jobs'],
        parameters: [
            new OA\Parameter(name: 'job', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Job returned'),
            new OA\Response(response: 404, description: 'Not found'),
        ],
    )]
    public function show(Job $job): JsonResponse
    {
        $job = $this->jobService->getForCompany(request()->user(), $job);
        $this->authorize('view', $job);

        return $this->successResponse(
            new CompanyJobResource($job),
            'Job retrieved.',
        );
    }

    #[OA\Put(
        path: '/company/jobs/{job}',
        summary: 'Update company job draft',
        security: [['sanctum' => []]],
        tags: ['Company Jobs'],
        parameters: [
            new OA\Parameter(name: 'job', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Job updated'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ],
    )]
    public function update(UpdateJobRequest $request, Job $job): JsonResponse
    {
        $job = $this->jobService->getForCompany($request->user(), $job);
        $this->authorize('update', $job);

        $job = $this->jobService->update($job, $request->validated());

        return $this->successResponse(
            new CompanyJobResource($job),
            'Job updated.',
        );
    }

    #[OA\Post(
        path: '/company/jobs/{job}/publish',
        summary: 'Publish company job draft',
        security: [['sanctum' => []]],
        tags: ['Company Jobs'],
        parameters: [
            new OA\Parameter(name: 'job', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Job published'),
            new OA\Response(response: 422, description: 'Invalid state'),
        ],
    )]
    public function publish(Job $job): JsonResponse
    {
        $job = $this->jobService->getForCompany(request()->user(), $job);
        $this->authorize('publish', $job);

        $job = $this->jobService->publish($job);

        return $this->successResponse(
            new CompanyJobResource($job),
            'Job published.',
        );
    }

    #[OA\Patch(
        path: '/company/jobs/{job}/unpublish',
        summary: 'Unpublish (close) a published company job',
        security: [['sanctum' => []]],
        tags: ['Company Jobs'],
        parameters: [
            new OA\Parameter(name: 'job', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Job unpublished'),
            new OA\Response(response: 422, description: 'Invalid state'),
        ],
    )]
    public function unpublish(Job $job): JsonResponse
    {
        $job = $this->jobService->getForCompany(request()->user(), $job);
        $this->authorize('unpublish', $job);

        $job = $this->jobService->unpublish($job);

        return $this->successResponse(
            new CompanyJobResource($job),
            'Job unpublished.',
        );
    }
}
