<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\SearchSkillRequest;
use App\Http\Resources\Candidate\SkillResource;
use App\Services\Candidate\CandidateProfileService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class SkillLookupController extends Controller
{
    public function __construct(
        private readonly CandidateProfileService $profileService,
    ) {}

    #[OA\Get(
        path: '/skills',
        summary: 'Search skills catalog for autocomplete',
        tags: ['Candidate'],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Skills returned'),
        ],
    )]
    public function index(SearchSkillRequest $request): JsonResponse
    {
        $skills = $this->profileService->searchSkills(
            $request->validated('q'),
            (int) ($request->validated('limit') ?? 20),
        );

        return $this->successResponse(
            SkillResource::collection($skills),
            'Skills retrieved.',
        );
    }
}
