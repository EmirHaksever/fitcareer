<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Company;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\ListApplicationsRequest;
use App\Http\Requests\Company\UpdateApplicationStatusRequest;
use App\Http\Resources\Company\CompanyApplicationResource;
use App\Models\Application;
use App\Services\Application\ApplicationService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationService $applicationService,
    ) {}

    #[OA\Get(
        path: '/company/applications',
        summary: 'List applications for company jobs',
        security: [['sanctum' => []]],
        tags: ['Company Applications'],
        responses: [
            new OA\Response(response: 200, description: 'Applications returned'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function index(ListApplicationsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Application::class);

        $status = $request->query('status');
        $statusEnum = is_string($status) ? ApplicationStatus::tryFrom($status) : null;

        $paginator = $this->applicationService->listForCompany(
            $request->user(),
            (int) $request->query('page', 1),
            (int) $request->query('per_page', 15),
            $request->filled('job_id') ? (int) $request->query('job_id') : null,
            $statusEnum,
        );

        return $this->successResponse([
            'items' => CompanyApplicationResource::collection($paginator->items())->resolve(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Company applications retrieved.');
    }

    #[OA\Get(
        path: '/company/applications/{application}',
        summary: 'Show a company application',
        security: [['sanctum' => []]],
        tags: ['Company Applications'],
        responses: [
            new OA\Response(response: 200, description: 'Application returned'),
            new OA\Response(response: 404, description: 'Not found'),
        ],
    )]
    public function show(int $application): JsonResponse
    {
        $resource = $this->applicationService->getForCompany(
            request()->user(),
            $application,
        );

        $this->authorize('view', $resource);

        return $this->successResponse(
            new CompanyApplicationResource($resource),
            'Application retrieved.',
        );
    }

    #[OA\Patch(
        path: '/company/applications/{application}/status',
        summary: 'Transition application status',
        security: [['sanctum' => []]],
        tags: ['Company Applications'],
        responses: [
            new OA\Response(response: 200, description: 'Status updated'),
            new OA\Response(response: 409, description: 'Invalid transition'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ],
    )]
    public function updateStatus(UpdateApplicationStatusRequest $request, int $application): JsonResponse
    {
        $existing = $this->applicationService->getForCompany(
            $request->user(),
            $application,
        );

        $this->authorize('transition', $existing);

        $updated = $this->applicationService->transitionForCompany(
            $request->user(),
            $application,
            $request->enum('status', ApplicationStatus::class),
            $request->validated('note'),
        );

        return $this->successResponse(
            new CompanyApplicationResource($updated),
            'Application status updated.',
        );
    }
}
