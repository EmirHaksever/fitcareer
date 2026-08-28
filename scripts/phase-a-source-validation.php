<?php

declare(strict_types=1);

/**
 * Phase A — live endpoint validation before seeding.
 */

$targets = [
    [
        'company' => 'iyzico',
        'provider' => 'lever',
        'slug' => 'iyzico',
        'url' => 'https://api.lever.co/v0/postings/iyzico?mode=json',
    ],
    [
        'company' => 'Grand Games',
        'provider' => 'lever',
        'slug' => 'grand',
        'url' => 'https://api.lever.co/v0/postings/grand?mode=json',
    ],
    [
        'company' => 'Zynga',
        'provider' => 'greenhouse',
        'slug' => 'zyngacareers',
        'url' => 'https://boards-api.greenhouse.io/v1/boards/zyngacareers/jobs?content=true',
    ],
    [
        'company' => 'OLIVER Agency',
        'provider' => 'greenhouse',
        'slug' => 'oliver',
        'url' => 'https://boards-api.greenhouse.io/v1/boards/oliver/jobs?content=true',
    ],
    [
        'company' => 'FERASET',
        'provider' => 'workable',
        'slug' => 'feraset',
        'url' => 'https://apply.workable.com/api/v1/widget/accounts/feraset?details=true',
    ],
];

$results = [];

foreach ($targets as $target) {
    $ch = curl_init($target['url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'User-Agent: FitCareer/1.0 (phase-a-validation)',
            'Accept: application/json',
        ],
    ]);

    $body = curl_exec($ch);
    $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $rawCount = 0;
    $trSignal = 0;
    $compatible = false;
    $error = null;

    if ($body === false) {
        $error = 'Request failed';
    } elseif ($httpStatus !== 200) {
        $error = 'HTTP '.$httpStatus;
    } else {
        $data = json_decode($body, true);
        if (! is_array($data)) {
            $error = 'Invalid JSON';
        } else {
            match ($target['provider']) {
                'lever' => (function () use ($data, &$rawCount, &$trSignal, &$compatible): void {
                    if (! array_is_list($data)) {
                        return;
                    }
                    $compatible = true;
                    $rawCount = count($data);
                    foreach ($data as $job) {
                        $loc = strtolower((string) ($job['categories']['location'] ?? ''));
                        if (str_contains($loc, 'istanbul') || str_contains($loc, 'turkey') || str_contains($loc, 'türkiye') || str_contains($loc, 'turkiye')) {
                            $trSignal++;
                        }
                    }
                })(),
                'greenhouse' => (function () use ($data, &$rawCount, &$trSignal, &$compatible): void {
                    if (! isset($data['jobs']) || ! is_array($data['jobs'])) {
                        return;
                    }
                    $compatible = true;
                    $rawCount = count($data['jobs']);
                    foreach ($data['jobs'] as $job) {
                        $loc = strtolower((string) ($job['location']['name'] ?? ''));
                        if (str_contains($loc, 'istanbul') || str_contains($loc, 'turkey') || str_contains($loc, 'türkiye') || str_contains($loc, 'turkiye')) {
                            $trSignal++;
                        }
                    }
                })(),
                'workable' => (function () use ($data, &$rawCount, &$trSignal, &$compatible): void {
                    if (! isset($data['jobs']) || ! is_array($data['jobs'])) {
                        return;
                    }
                    $compatible = true;
                    $rawCount = count($data['jobs']);
                    foreach ($data['jobs'] as $job) {
                        $city = strtolower((string) ($job['location']['city'] ?? ''));
                        $country = strtolower((string) ($job['location']['country'] ?? ''));
                        $loc = $city.' '.$country;
                        if (str_contains($loc, 'istanbul') || str_contains($loc, 'turkey') || str_contains($loc, 'türkiye') || str_contains($loc, 'turkiye')) {
                            $trSignal++;
                        }
                    }
                })(),
                default => null,
            };
        }
    }

    $results[] = array_merge($target, [
        'http_status' => $httpStatus,
        'reachable' => $body !== false && $httpStatus === 200,
        'raw_jobs' => $rawCount,
        'turkey_relevance_signal' => $trSignal,
        'adapter_compatible' => $compatible,
        'error' => $error,
        'verdict' => ($error === null && $compatible && $rawCount > 0) ? 'VALID' : 'INVALID',
    ]);
}

echo json_encode(['generated_at' => date('c'), 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
