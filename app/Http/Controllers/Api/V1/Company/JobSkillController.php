<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Job\AttachJobSkillRequest;
use App\Http\Requests\Job\SyncJobSkillsRequest;
use App\Http\Resources\Job\JobSkillResource;
use App\Models\Job;
use App\Models\Skill;
use App\Services\Job\JobService;
use App\Services\Job\JobSkillService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class JobSkillController extends Controller
{
    public function __construct(
        private readonly JobService $jobService,
        private readonly JobSkillService $jobSkillService,
    ) {}

    #[OA\Get(
        path: '/company/jobs/{job}/skills',
        summary: 'List skills for a company job',
        security: [['sanctum' => []]],
        tags: ['Company Jobs'],
        responses: [
            new OA\Response(response: 200, description: 'Skills returned'),
            new OA\Response(response: 404, description: 'Not found'),
        ],
    )]
    public function index(Job $job): JsonResponse
    {
        $job = $this->jobService->getForCompany(request()->user(), $job);
        $this->authorize('manageSkills', $job);

        $skills = $this->jobSkillService->list($job);

        return $this->successResponse(
            JobSkillResource::collection($skills)->resolve(),
            'Job skills retrieved.',
        );
    }

    #[OA\Post(
        path: '/company/jobs/{job}/skills',
        summary: 'Attach a skill to a company job',
        security: [['sanctum' => []]],
        tags: ['Company Jobs'],
        responses: [
            new OA\Response(response: 201, description: 'Skill attached'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ],
    )]
    public function store(AttachJobSkillRequest $request, Job $job): JsonResponse
    {
        $job = $this->jobService->getForCompany($request->user(), $job);
        $this->authorize('manageSkills', $job);

        $jobSkill = $this->jobSkillService->attach($job, $request->validated());

        return $this->createdResponse(
            new JobSkillResource($jobSkill),
            'Job skill attached.',
        );
    }

    #[OA\Put(
        path: '/company/jobs/{job}/skills',
        summary: 'Replace all skills for a company job',
        security: [['sanctum' => []]],
        tags: ['Company Jobs'],
        responses: [
            new OA\Response(response: 200, description: 'Skills replaced'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ],
    )]
    public function sync(SyncJobSkillsRequest $request, Job $job): JsonResponse
    {
        $job = $this->jobService->getForCompany($request->user(), $job);
        $this->authorize('manageSkills', $job);

        $skills = $this->jobSkillService->sync($job, $request->validated('skills'));

        return $this->successResponse(
            JobSkillResource::collection($skills)->resolve(),
            'Job skills updated.',
        );
    }

    #[OA\Delete(
        path: '/company/jobs/{job}/skills/{skill}',
        summary: 'Detach a skill from a company job',
        security: [['sanctum' => []]],
        tags: ['Company Jobs'],
        responses: [
            new OA\Response(response: 200, description: 'Skill detached'),
            new OA\Response(response: 404, description: 'Not found'),
        ],
    )]
    public function destroy(Job $job, Skill $skill): JsonResponse
    {
        $job = $this->jobService->getForCompany(request()->user(), $job);
        $this->authorize('manageSkills', $job);

        $this->jobSkillService->detach($job, $skill->id);

        return $this->successResponse(null, 'Job skill detached.');
    }
}
