<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\UpdateCompanyProfileRequest;
use App\Http\Requests\Company\UploadCompanyLogoRequest;
use App\Http\Resources\Company\CompanyResource;
use App\Services\Company\CompanyService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    public function __construct(
        private readonly CompanyService $companyService,
    ) {}

    #[OA\Get(
        path: '/company/profile',
        summary: 'Get authenticated company profile',
        security: [['sanctum' => []]],
        tags: ['Company'],
        responses: [
            new OA\Response(response: 200, description: 'Company profile returned'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function show(): JsonResponse
    {
        $company = $this->companyService->getForUser(request()->user());

        return $this->successResponse(
            new CompanyResource($company),
            'Company profile retrieved.',
        );
    }

    #[OA\Put(
        path: '/company/profile',
        summary: 'Update authenticated company profile',
        security: [['sanctum' => []]],
        tags: ['Company'],
        responses: [
            new OA\Response(response: 200, description: 'Company profile updated'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ],
    )]
    public function update(UpdateCompanyProfileRequest $request): JsonResponse
    {
        $company = $this->companyService->updateProfile(
            $request->user(),
            $request->validated(),
        );

        return $this->successResponse(
            new CompanyResource($company),
            'Company profile updated.',
        );
    }

    #[OA\Post(
        path: '/company/profile/logo',
        summary: 'Upload company logo',
        security: [['sanctum' => []]],
        tags: ['Company'],
        responses: [
            new OA\Response(response: 200, description: 'Logo uploaded'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ],
    )]
    public function uploadLogo(UploadCompanyLogoRequest $request): JsonResponse
    {
        $company = $this->companyService->uploadLogo(
            $request->user(),
            $request->file('logo'),
        );

        return $this->successResponse(
            new CompanyResource($company),
            'Company logo uploaded.',
        );
    }

    #[OA\Delete(
        path: '/company/profile/logo',
        summary: 'Delete company logo',
        security: [['sanctum' => []]],
        tags: ['Company'],
        responses: [
            new OA\Response(response: 200, description: 'Logo deleted'),
        ],
    )]
    public function deleteLogo(): JsonResponse
    {
        $company = $this->companyService->deleteLogo(request()->user());

        return $this->successResponse(
            new CompanyResource($company),
            'Company logo deleted.',
        );
    }
}
