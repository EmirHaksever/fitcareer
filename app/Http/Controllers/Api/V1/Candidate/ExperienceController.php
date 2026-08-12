<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreExperienceRequest;
use App\Http\Requests\Candidate\UpdateExperienceRequest;
use App\Http\Resources\Candidate\CandidateExperienceResource;
use App\Services\Candidate\CandidateProfileService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ExperienceController extends Controller
{
    public function __construct(
        private readonly CandidateProfileService $profileService,
    ) {}

    #[OA\Get(path: '/candidate/experiences', summary: 'List candidate experiences', security: [['sanctum' => []]], tags: ['Candidate'], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function index(): JsonResponse
    {
        $experiences = $this->profileService->listExperiences(request()->user());

        return $this->successResponse(
            CandidateExperienceResource::collection($experiences),
            'Experiences retrieved.',
        );
    }

    #[OA\Post(path: '/candidate/experiences', summary: 'Create candidate experience', security: [['sanctum' => []]], tags: ['Candidate'], responses: [new OA\Response(response: 201, description: 'Created')])]
    public function store(StoreExperienceRequest $request): JsonResponse
    {
        $experience = $this->profileService->createExperience(
            $request->user(),
            $request->validated(),
        );

        return $this->createdResponse(
            new CandidateExperienceResource($experience),
            'Experience created.',
        );
    }

    #[OA\Put(path: '/candidate/experiences/{experience}', summary: 'Update candidate experience', security: [['sanctum' => []]], tags: ['Candidate'], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function update(UpdateExperienceRequest $request, int $experience): JsonResponse
    {
        $updated = $this->profileService->updateExperience(
            $request->user(),
            $experience,
            $request->validated(),
        );

        return $this->successResponse(
            new CandidateExperienceResource($updated),
            'Experience updated.',
        );
    }

    #[OA\Delete(path: '/candidate/experiences/{experience}', summary: 'Delete candidate experience', security: [['sanctum' => []]], tags: ['Candidate'], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function destroy(int $experience): JsonResponse
    {
        $this->profileService->deleteExperience(request()->user(), $experience);

        return $this->successResponse(null, 'Experience deleted.');
    }
}
