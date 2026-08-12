<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Job\UpdateJobFitScoreSettingsRequest;
use App\Models\Job;
use App\Services\Job\JobFitScoreSettingsService;
use App\Services\Job\JobService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class JobFitScoreSettingsController extends Controller
{
    public function __construct(
        private readonly JobService $jobService,
        private readonly JobFitScoreSettingsService $jobFitScoreSettingsService,
    ) {}

    #[OA\Get(
        path: '/company/jobs/{job}/fit-score-settings',
        summary: 'Get effective fit score weights for a company job',
        security: [['sanctum' => []]],
        tags: ['Company Jobs'],
        responses: [
            new OA\Response(response: 200, description: 'Settings returned'),
            new OA\Response(response: 404, description: 'Not found'),
        ],
    )]
    public function show(Job $job): JsonResponse
    {
        $job = $this->jobService->getForCompany(request()->user(), $job);
        $this->authorize('manageFitScoreSettings', $job);

        return $this->successResponse(
            $this->jobFitScoreSettingsService->get($job),
            'Job fit score settings retrieved.',
        );
    }

    #[OA\Put(
        path: '/company/jobs/{job}/fit-score-settings',
        summary: 'Update custom fit score weights for a company job',
        security: [['sanctum' => []]],
        tags: ['Company Jobs'],
        responses: [
            new OA\Response(response: 200, description: 'Settings updated'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ],
    )]
    public function update(UpdateJobFitScoreSettingsRequest $request, Job $job): JsonResponse
    {
        $job = $this->jobService->getForCompany($request->user(), $job);
        $this->authorize('manageFitScoreSettings', $job);

        $settings = $this->jobFitScoreSettingsService->update(
            $job,
            $request->validated('weights'),
        );

        return $this->successResponse(
            $settings,
            'Job fit score settings updated.',
        );
    }
}
