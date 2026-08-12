<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\AttachSkillRequest;
use App\Http\Requests\Candidate\UpdateSkillRequest;
use App\Http\Resources\Candidate\CandidateSkillResource;
use App\Services\Candidate\CandidateProfileService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class SkillController extends Controller
{
    public function __construct(
        private readonly CandidateProfileService $profileService,
    ) {}

    #[OA\Get(path: '/candidate/skills', summary: 'List candidate skills', security: [['sanctum' => []]], tags: ['Candidate'], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function index(): JsonResponse
    {
        $skills = $this->profileService->listSkills(request()->user());

        return $this->successResponse(
            CandidateSkillResource::collection($skills),
            'Skills retrieved.',
        );
    }

    #[OA\Post(path: '/candidate/skills', summary: 'Attach skill to candidate profile', security: [['sanctum' => []]], tags: ['Candidate'], responses: [new OA\Response(response: 201, description: 'Created')])]
    public function store(AttachSkillRequest $request): JsonResponse
    {
        $candidateSkill = $this->profileService->attachSkill(
            $request->user(),
            $request->validated(),
        );

        return $this->createdResponse(
            new CandidateSkillResource($candidateSkill),
            'Skill attached.',
        );
    }

    #[OA\Put(path: '/candidate/skills/{candidateSkill}', summary: 'Update candidate skill pivot', security: [['sanctum' => []]], tags: ['Candidate'], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function update(UpdateSkillRequest $request, int $candidateSkill): JsonResponse
    {
        $updated = $this->profileService->updateSkill(
            $request->user(),
            $candidateSkill,
            $request->validated(),
        );

        return $this->successResponse(
            new CandidateSkillResource($updated),
            'Skill updated.',
        );
    }

    #[OA\Delete(path: '/candidate/skills/{candidateSkill}', summary: 'Detach skill from candidate profile', security: [['sanctum' => []]], tags: ['Candidate'], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function destroy(int $candidateSkill): JsonResponse
    {
        $this->profileService->detachSkill(request()->user(), $candidateSkill);

        return $this->successResponse(null, 'Skill detached.');
    }
}
