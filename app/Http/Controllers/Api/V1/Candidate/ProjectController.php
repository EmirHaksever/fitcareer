<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreProjectRequest;
use App\Http\Requests\Candidate\UpdateProjectRequest;
use App\Http\Resources\Candidate\CandidateProjectResource;
use App\Services\Candidate\CandidateProfileService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ProjectController extends Controller
{
    public function __construct(
        private readonly CandidateProfileService $profileService,
    ) {}

    #[OA\Get(path: '/candidate/projects', summary: 'List candidate projects', security: [['sanctum' => []]], tags: ['Candidate'], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function index(): JsonResponse
    {
        $projects = $this->profileService->listProjects(request()->user());

        return $this->successResponse(
            CandidateProjectResource::collection($projects),
            'Projects retrieved.',
        );
    }

    #[OA\Post(path: '/candidate/projects', summary: 'Create candidate project', security: [['sanctum' => []]], tags: ['Candidate'], responses: [new OA\Response(response: 201, description: 'Created')])]
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->profileService->createProject(
            $request->user(),
            $request->validated(),
        );

        return $this->createdResponse(
            new CandidateProjectResource($project),
            'Project created.',
        );
    }

    #[OA\Put(path: '/candidate/projects/{project}', summary: 'Update candidate project', security: [['sanctum' => []]], tags: ['Candidate'], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function update(UpdateProjectRequest $request, int $project): JsonResponse
    {
        $updated = $this->profileService->updateProject(
            $request->user(),
            $project,
            $request->validated(),
        );

        return $this->successResponse(
            new CandidateProjectResource($updated),
            'Project updated.',
        );
    }

    #[OA\Delete(path: '/candidate/projects/{project}', summary: 'Delete candidate project', security: [['sanctum' => []]], tags: ['Candidate'], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function destroy(int $project): JsonResponse
    {
        $this->profileService->deleteProject(request()->user(), $project);

        return $this->successResponse(null, 'Project deleted.');
    }
}
