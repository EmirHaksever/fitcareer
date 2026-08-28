<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\JobStatus;
use App\Enums\TrustLabel;
use App\Models\Job;

$query = Job::query()
    ->where('status', JobStatus::Published)
    ->where(function ($q): void {
        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
    });

echo json_encode([
    'total_jobs' => $query->count(),
    'trusted_jobs' => (clone $query)->where('trust_label', TrustLabel::Verified)->count(),
    'suspicious_jobs' => (clone $query)->whereIn('trust_label', [
        TrustLabel::Suspicious,
        TrustLabel::LowTrust,
    ])->count(),
    'trust_distribution' => (clone $query)
        ->selectRaw('trust_label, COUNT(*) as aggregate')
        ->groupBy('trust_label')
        ->pluck('aggregate', 'trust_label'),
], JSON_PRETTY_PRINT).PHP_EOL;
