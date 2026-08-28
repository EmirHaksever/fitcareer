<?php

declare(strict_types=1);

/**
 * Phase G — refresh inventory timestamps on the audit JSON without wiping decisions.
 * Read-only DB. Does not import or seed.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobSource;

$path = __DIR__.'/../FITCAREER_PHASE_G_TECH_JOB_SUPPLY_AUDIT.json';
$json = json_decode((string) file_get_contents($path), true);
if (! is_array($json)) {
    fwrite(STDERR, "Audit JSON missing or invalid\n");
    exit(1);
}

$published = Job::query()->where('status', JobStatus::Published);
$json['inventory']['published_jobs'] = (clone $published)->count();
$json['inventory']['origin_internal'] = (clone $published)->where('source', JobOrigin::Internal)->count();
$json['inventory']['origin_scraped'] = (clone $published)->where('source', JobOrigin::Scraped)->count();
$json['inventory']['active_sources'] = JobSource::query()->where('is_active', true)->count();
$json['inventory']['companies'] = Company::count();
$json['inventory']['companies_verified'] = Company::query()->where('verification_status', 'verified')->count();
$json['inventory']['companies_pending'] = Company::query()->where('verification_status', 'pending')->count();
$json['inventory']['companies_unverified'] = Company::query()->where('verification_status', 'unverified')->count();
$json['audit']['inventory_refreshed_at'] = now()->toIso8601String();

file_put_contents($path, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
echo "Inventory timestamps refreshed; decisions preserved\n";
