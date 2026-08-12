<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Resources\Job\JobListResource;
use App\Models\Job;
use App\Services\Candidate\SavedJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedJobController extends Controller
{
    public function __construct(
        private readonly SavedJobService $savedJobService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->savedJobService->listForUser(
            $request->user(),
            (int) $request->query('page', 1),
            (int) $request->query('per_page', 15),
        );

        $candidateProfileId = $request->user()->candidateProfile?->id;

        return $this->successResponse([
            'items' => collect($paginator->items())
                ->map(fn ($savedJob) => (new JobListResource($savedJob->job, $candidateProfileId))->resolve())
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Saved jobs retrieved.');
    }

    public function ids(Request $request): JsonResponse
    {
        return $this->successResponse([
            'job_ids' => $this->savedJobService->listJobIdsForUser($request->user()),
        ], 'Saved job ids retrieved.');
    }

    public function store(Request $request, Job $job): JsonResponse
    {
        $saved = $this->savedJobService->save($request->user(), $job);

        return $this->successResponse([
            'job_id' => $saved->job_id,
            'saved_at' => $saved->saved_at?->toIso8601String(),
        ], 'Job saved.', 201);
    }

    public function destroy(Request $request, Job $job): JsonResponse
    {
        $this->savedJobService->remove($request->user(), $job);

        return $this->successResponse(null, 'Job removed from saved list.');
    }
}
