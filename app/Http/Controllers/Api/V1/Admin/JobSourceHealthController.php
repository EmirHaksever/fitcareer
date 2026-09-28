<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Http\Controllers\Controller;
use App\Models\JobSource;
use App\Services\Scraper\JobSourceHealthService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class JobSourceHealthController extends Controller
{
    public function __construct(
        private readonly JobSourceHealthService $healthService,
    ) {}

    #[OA\Get(
        path: '/admin/job-sources/health',
        summary: 'List job source health snapshots',
        security: [['sanctum' => []]],
        tags: ['Admin'],
        responses: [
            new OA\Response(response: 200, description: 'Job source health returned'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function index(): JsonResponse
    {
        $sources = JobSource::query()
            ->withCount(['jobs as active_published_jobs_count' => function ($query): void {
                $query
                    ->where('source', JobOrigin::Scraped)
                    ->where('status', JobStatus::Published);
            }])
            ->orderBy('name')
            ->get();

        $items = $sources->map(function (JobSource $source): array {
            $snapshot = $this->healthService->snapshot($source);
            $staleAfterHours = (int) config('scraper.stale_after_hours', 48);
            $lastSuccessAt = $source->last_success_at;
            $latestRunStatus = $snapshot['latest_run']['status'] ?? null;

            $healthStatus = match (true) {
                ! $source->is_active => 'inactive',
                $source->consecutive_failures >= 3 => 'error',
                $latestRunStatus === 'partial' => 'degraded',
                $lastSuccessAt === null => 'pending',
                $lastSuccessAt->lt(now()->subHours($staleAfterHours)) => 'stale',
                default => 'healthy',
            };

            return $snapshot + [
                'base_url' => $source->base_url,
                'type' => $source->type?->value,
                'refresh_interval_minutes' => (int) ($source->config['refresh_interval_minutes']
                    ?? config('scraper.default_refresh_interval_minutes')),
                'stale_after_hours' => $staleAfterHours,
                'health_status' => $healthStatus,
                'active_published_jobs_count' => (int) $source->active_published_jobs_count,
            ];
        })->values()->all();

        return $this->successResponse([
            'items' => $items,
            'summary' => [
                'total' => count($items),
                'active' => collect($items)->where('is_active', true)->count(),
                'healthy' => collect($items)->where('health_status', 'healthy')->count(),
                'attention' => collect($items)->whereIn('health_status', ['error', 'degraded', 'stale', 'pending'])->count(),
            ],
        ], 'Job source health retrieved.');
    }
}
