<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Company\CompanyResource;
use App\Models\Company;
use App\Services\Company\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class CompanyVerificationController extends Controller
{
    public function __construct(
        private readonly CompanyService $companyService,
    ) {}

    #[OA\Get(
        path: '/admin/companies/pending',
        summary: 'List companies with a pending verification request',
        security: [['sanctum' => []]],
        tags: ['Admin'],
        responses: [
            new OA\Response(response: 200, description: 'Pending companies returned'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function pending(Request $request): JsonResponse
    {
        $paginator = $this->companyService->listPending(
            (int) $request->query('page', 1),
            (int) $request->query('per_page', 15),
        );

        return $this->successResponse([
            'items' => CompanyResource::collection($paginator->items())->resolve(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Pending companies retrieved.');
    }

    #[OA\Post(
        path: '/admin/companies/{company}/verify',
        summary: 'Approve or reject a pending company verification request',
        security: [['sanctum' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Verification decision applied'),
            new OA\Response(response: 422, description: 'Invalid state'),
        ],
    )]
    public function verify(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:approve,reject'],
        ]);

        try {
            $company = $this->companyService->applyOperationalVerification(
                $company,
                (string) $validated['action'],
            );
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return $this->successResponse(
            new CompanyResource($company),
            'Company verification updated.',
        );
    }
}
