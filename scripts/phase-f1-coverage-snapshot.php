<?php

declare(strict_types=1);

/**
 * Phase F.1 read-only coverage snapshot for experience_level + QA search.
 * No DB writes.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\JobStatus;
use App\Models\Job;
use App\Repositories\Contracts\JobSearchRepositoryInterface;
use App\DTOs\JobSearchQuery;
use App\Services\Scraper\LocationClassificationService;

$now = now();
$published = Job::query()
    ->where('status', JobStatus::Published)
    ->where(function ($q) use ($now): void {
        $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
    });

$totalPublished = (clone $published)->count();
$nullExp = (clone $published)->whereNull('experience_level')->count();
$filledExp = $totalPublished - $nullExp;

$tr = Job::query()
    ->where('status', JobStatus::Published)
    ->where(function ($q) use ($now): void {
        $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
    });
app(LocationClassificationService::class)->applyTurkeyRelevantScope($tr, false);
$trCount = $tr->count();
$trNull = (clone $tr)->whereNull('experience_level')->count();

$search = app(JobSearchRepositoryInterface::class);
$qa = $search->search(JobSearchQuery::fromValidatedInput(['keyword' => 'QA', 'per_page' => 1]));
$quality = $search->search(JobSearchQuery::fromValidatedInput(['keyword' => 'Quality Assurance', 'per_page' => 1]));
$frontend = $search->search(JobSearchQuery::fromValidatedInput(['keyword' => 'frontend', 'per_page' => 1]));
$devops = $search->search(JobSearchQuery::fromValidatedInput(['keyword' => 'DevOps', 'per_page' => 1]));

echo json_encode([
    'published' => $totalPublished,
    'experience_null' => $nullExp,
    'experience_filled' => $filledExp,
    'experience_coverage_pct' => $totalPublished > 0 ? round(100 * $filledExp / $totalPublished, 1) : 0,
    'turkey_visible' => $trCount,
    'turkey_experience_null' => $trNull,
    'search_qa' => $qa->total(),
    'search_quality_assurance' => $quality->total(),
    'search_frontend' => $frontend->total(),
    'search_devops' => $devops->total(),
    'job_count' => Job::count(),
    'job_source_count' => \App\Models\JobSource::count(),
], JSON_PRETTY_PRINT)."\n";
