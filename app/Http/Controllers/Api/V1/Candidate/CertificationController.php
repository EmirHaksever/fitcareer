<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreCertificationRequest;
use App\Http\Requests\Candidate\UpdateCertificationRequest;
use App\Http\Resources\Candidate\CandidateCertificationResource;
use App\Services\Candidate\CandidateProfileService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class CertificationController extends Controller
{
    public function __construct(
        private readonly CandidateProfileService $profileService,
    ) {}

    #[OA\Get(path: '/candidate/certifications', summary: 'List candidate certifications', security: [['sanctum' => []]], tags: ['Candidate'], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function index(): JsonResponse
    {
        $certifications = $this->profileService->listCertifications(request()->user());

        return $this->successResponse(
            CandidateCertificationResource::collection($certifications),
            'Certifications retrieved.',
        );
    }

    #[OA\Post(path: '/candidate/certifications', summary: 'Create candidate certification', security: [['sanctum' => []]], tags: ['Candidate'], responses: [new OA\Response(response: 201, description: 'Created')])]
    public function store(StoreCertificationRequest $request): JsonResponse
    {
        $certification = $this->profileService->createCertification(
            $request->user(),
            $request->validated(),
        );

        return $this->createdResponse(
            new CandidateCertificationResource($certification),
            'Certification created.',
        );
    }

    #[OA\Put(path: '/candidate/certifications/{certification}', summary: 'Update candidate certification', security: [['sanctum' => []]], tags: ['Candidate'], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function update(UpdateCertificationRequest $request, int $certification): JsonResponse
    {
        $updated = $this->profileService->updateCertification(
            $request->user(),
            $certification,
            $request->validated(),
        );

        return $this->successResponse(
            new CandidateCertificationResource($updated),
            'Certification updated.',
        );
    }

    #[OA\Delete(path: '/candidate/certifications/{certification}', summary: 'Delete candidate certification', security: [['sanctum' => []]], tags: ['Candidate'], responses: [new OA\Response(response: 200, description: 'OK')])]
    public function destroy(int $certification): JsonResponse
    {
        $this->profileService->deleteCertification(request()->user(), $certification);

        return $this->successResponse(null, 'Certification deleted.');
    }
}
