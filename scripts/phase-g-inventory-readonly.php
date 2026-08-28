<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Models\Company;
use App\Models\Job;

$published = Job::query()->where('status', JobStatus::Published)->get(['id', 'source', 'company_id']);
$byOrigin = $published->groupBy(fn ($j) => $j->source instanceof JobOrigin ? $j->source->value : (string) $j->source);

echo 'published='.$published->count()."\n";
foreach ($byOrigin as $k => $rows) {
    echo 'origin_'.$k.'='.$rows->count()."\n";
}
echo 'companies='.Company::count()."\n";
echo 'verified='.Company::query()->where('verification_status', 'verified')->count()."\n";
echo 'pending='.Company::query()->where('verification_status', 'pending')->count()."\n";
echo 'unverified='.Company::query()->where('verification_status', 'unverified')->count()."\n";
echo 'rejected='.Company::query()->where('verification_status', 'rejected')->count()."\n";
echo 'jobs_now='.Job::count()."\n";
echo 'sources_now='.App\Models\JobSource::count()."\n";
