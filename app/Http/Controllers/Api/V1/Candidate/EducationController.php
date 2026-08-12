<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreEducationRequest;
use App\Http\Requests\Candidate\UpdateEducationRequest;
use App\Http\Resources\Candidate\CandidateEducationResource;
use App\Services\Candidate\CandidateProfileService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class EducationController extends Controller
{
    public function __construct(
        private readonly CandidateProfileService $profileService,
    ) {}

    #[OA\Get(path: '/candidate/educations', summary: 'List candidate educations', security: [['sanctum' => []]], tags: ['Candidate'], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function index(): JsonResponse
    {
        $educations = $this->profileService->listEducations(request()->user());

        return $this->successResponse(
            CandidateEducationResource::collection($educations),
            'Educations retrieved.',
        );
    }

    #[OA\Post(path: '/candidate/educations', summary: 'Create candidate education', security: [['sanctum' => []]], tags: ['Candidate'], responses: [new OA\Response(response: 201, description: 'Created')])]
    public function store(StoreEducationRequest $request): JsonResponse
    {
        $education = $this->profileService->createEducation(
            $request->user(),
            $request->validated(),
        );

        return $this->createdResponse(
            new CandidateEducationResource($education),
            'Education created.',
        );
    }

    #[OA\Put(path: '/candidate/educations/{education}', summary: 'Update candidate education', security: [['sanctum' => []]], tags: ['Candidate'], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function update(UpdateEducationRequest $request, int $education): JsonResponse
    {
        $updated = $this->profileService->updateEducation(
            $request->user(),
            $education,
            $request->validated(),
        );

        return $this->successResponse(
            new CandidateEducationResource($updated),
            'Education updated.',
        );
    }

    #[OA\Delete(path: '/candidate/educations/{education}', summary: 'Delete candidate education', security: [['sanctum' => []]], tags: ['Candidate'], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function destroy(int $education): JsonResponse
    {
        $this->profileService->deleteEducation(request()->user(), $education);

        return $this->successResponse(null, 'Education deleted.');
    }
}
