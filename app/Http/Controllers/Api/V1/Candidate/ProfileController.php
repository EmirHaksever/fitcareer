<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\UpdateProfileRequest;
use App\Http\Requests\Candidate\UploadProfilePhotoRequest;
use App\Http\Resources\Candidate\CandidateProfileResource;
use App\Services\Candidate\CandidateProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function __construct(
        private readonly CandidateProfileService $profileService,
    ) {}

    #[OA\Get(
        path: '/candidate/profile',
        summary: 'Get authenticated candidate profile',
        security: [['sanctum' => []]],
        tags: ['Candidate'],
        responses: [
            new OA\Response(response: 200, description: 'Profile returned'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function show(): JsonResponse
    {
        $profile = $this->profileService->getForUser(request()->user());

        return $this->successResponse(
            new CandidateProfileResource($profile),
            'Candidate profile retrieved.',
        );
    }

    #[OA\Put(
        path: '/candidate/profile',
        summary: 'Update authenticated candidate profile',
        security: [['sanctum' => []]],
        tags: ['Candidate'],
        responses: [
            new OA\Response(response: 200, description: 'Profile updated'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ],
    )]
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $profile = $this->profileService->updateProfile(
            $request->user(),
            $request->validated(),
        );

        return $this->successResponse(
            new CandidateProfileResource($profile),
            'Candidate profile updated.',
        );
    }

    #[OA\Post(
        path: '/candidate/profile/photo',
        summary: 'Upload candidate profile photo',
        security: [['sanctum' => []]],
        tags: ['Candidate'],
        responses: [
            new OA\Response(response: 200, description: 'Photo uploaded'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ],
    )]
    public function uploadPhoto(UploadProfilePhotoRequest $request): JsonResponse
    {
        $profile = $this->profileService->uploadProfilePhoto(
            $request->user(),
            $request->file('photo'),
        );

        return $this->successResponse(
            new CandidateProfileResource($profile),
            'Profile photo uploaded.',
        );
    }

    #[OA\Delete(
        path: '/candidate/profile/photo',
        summary: 'Delete candidate profile photo',
        security: [['sanctum' => []]],
        tags: ['Candidate'],
        responses: [
            new OA\Response(response: 200, description: 'Photo deleted'),
        ],
    )]
    public function deletePhoto(): JsonResponse
    {
        $profile = $this->profileService->deleteProfilePhoto(request()->user());

        return $this->successResponse(
            new CandidateProfileResource($profile),
            'Profile photo deleted.',
        );
    }

    public function showPhoto(): StreamedResponse|JsonResponse
    {
        $profile = $this->profileService->getForUser(request()->user());

        if ($profile->profile_photo_path === null) {
            return $this->notFoundResponse('Profile photo not found.');
        }

        $disk = Storage::disk((string) config('candidate.photo.storage_disk'));

        if (! $disk->exists($profile->profile_photo_path)) {
            return $this->notFoundResponse('Profile photo not found.');
        }

        return $disk->response($profile->profile_photo_path);
    }
}
