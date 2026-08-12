<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Job;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\Job\JobDetailResource;
use App\Services\Job\JobService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class JobController extends Controller
{
    public function __construct(
        private readonly JobService $jobService,
    ) {}

    #[OA\Get(
        path: '/jobs/{slug}',
        summary: 'Get published job detail by slug',
        tags: ['Jobs'],
        parameters: [
            new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Job detail returned'),
            new OA\Response(response: 404, description: 'Not found'),
        ],
    )]
    public function show(string $slug): JsonResponse
    {
        $user = request()->user();
        $candidateProfileId = null;

        if ($user !== null && $user->role === UserRole::Candidate) {
            $candidateProfileId = $user->candidateProfile?->id;
        }

        $job = $this->jobService->getPublishedBySlug($slug, $candidateProfileId);

        return $this->successResponse(
            new JobDetailResource($job, $candidateProfileId),
            'Job retrieved.',
        );
    }
}
