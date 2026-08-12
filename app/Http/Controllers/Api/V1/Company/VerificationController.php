<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\Company\CompanyResource;
use App\Services\Company\CompanyService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class VerificationController extends Controller
{
    public function __construct(
        private readonly CompanyService $companyService,
    ) {}

    #[OA\Post(
        path: '/company/verification/request',
        summary: 'Request company verification review',
        security: [['sanctum' => []]],
        tags: ['Company'],
        responses: [
            new OA\Response(response: 200, description: 'Verification requested'),
            new OA\Response(response: 422, description: 'Invalid state'),
        ],
    )]
    public function request(): JsonResponse
    {
        $company = $this->companyService->requestVerification(request()->user());

        return $this->successResponse(
            new CompanyResource($company),
            'Verification request submitted.',
        );
    }
}
