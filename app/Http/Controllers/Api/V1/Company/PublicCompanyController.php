<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\Company\PublicCompanyResource;
use App\Services\Company\CompanyService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class PublicCompanyController extends Controller
{
    public function __construct(
        private readonly CompanyService $companyService,
    ) {}

    #[OA\Get(
        path: '/companies/{slug}',
        summary: 'Get public company profile by slug',
        tags: ['Company'],
        parameters: [
            new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Public company profile returned'),
            new OA\Response(response: 404, description: 'Not found'),
        ],
    )]
    public function show(string $slug): JsonResponse
    {
        $company = $this->companyService->getPublicBySlug($slug);

        return $this->successResponse(
            new PublicCompanyResource($company),
            'Company profile retrieved.',
        );
    }
}
