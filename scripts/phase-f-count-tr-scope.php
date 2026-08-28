<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Job;
use App\Enums\JobStatus;
use App\Services\Scraper\LocationClassificationService;
$b = Job::query()->where('status', JobStatus::Published)->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
app(LocationClassificationService::class)->applyTurkeyRelevantScope($b, false);
echo 'user_visible_tr_jobs='.$b->count().PHP_EOL;
