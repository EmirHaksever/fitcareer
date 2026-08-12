<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\UploadCvRequest;
use App\Http\Resources\Candidate\CandidateProfileResource;
use App\Http\Resources\Candidate\CvMetadataResource;
use App\Services\Candidate\CandidateProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CvController extends Controller
{
    public function __construct(
        private readonly CandidateProfileService $profileService,
    ) {}

    #[OA\Get(
        path: '/candidate/cv',
        summary: 'Get CV metadata for authenticated candidate',
        security: [['sanctum' => []]],
        tags: ['Candidate'],
        responses: [
            new OA\Response(response: 200, description: 'CV metadata returned'),
        ],
    )]
    public function show(): JsonResponse
    {
        $profile = $this->profileService->getForUser(request()->user());

        return $this->successResponse(
            new CvMetadataResource($profile),
            'CV metadata retrieved.',
        );
    }

    #[OA\Post(
        path: '/candidate/cv',
        summary: 'Upload or replace candidate CV',
        security: [['sanctum' => []]],
        tags: ['Candidate'],
        responses: [
            new OA\Response(response: 200, description: 'CV uploaded'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ],
    )]
    public function store(UploadCvRequest $request): JsonResponse
    {
        $profile = $this->profileService->uploadCv(
            $request->user(),
            $request->file('cv'),
        );

        return $this->successResponse(
            new CandidateProfileResource($profile),
            'CV uploaded successfully.',
        );
    }

    #[OA\Delete(
        path: '/candidate/cv',
        summary: 'Delete candidate CV',
        security: [['sanctum' => []]],
        tags: ['Candidate'],
        responses: [
            new OA\Response(response: 200, description: 'CV deleted'),
        ],
    )]
    public function destroy(): JsonResponse
    {
        $profile = $this->profileService->deleteCv(request()->user());

        return $this->successResponse(
            new CvMetadataResource($profile),
            'CV deleted successfully.',
        );
    }

    public function download(): StreamedResponse|JsonResponse
    {
        $profile = $this->profileService->getForUser(request()->user());

        if ($profile->cv_file_path === null) {
            return $this->notFoundResponse('CV not found.');
        }

        $disk = Storage::disk((string) config('candidate.cv.storage_disk'));

        if (! $disk->exists($profile->cv_file_path)) {
            return $this->notFoundResponse('CV not found.');
        }

        $filename = is_array($profile->cv_parsed_data)
            ? ($profile->cv_parsed_data['source_filename'] ?? basename($profile->cv_file_path))
            : basename($profile->cv_file_path);

        return $disk->download($profile->cv_file_path, $filename);
    }
}
