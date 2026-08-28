<?php

declare(strict_types=1);

/**
 * Shared helpers for ATS coverage discovery scripts (diagnostic only).
 */

const ATS_DISCOVERY_USER_AGENT = 'FitCareer-ATS-Coverage-Discovery/1.0 (+diagnostic)';
const ATS_MAX_POSTING_AGE_DAYS = 365;

/** @return list<string> */
function turkeyCityPatterns(): array
{
    return [
        'turkey', 'türkiye', 'turkiye', 'istanbul', 'i̇stanbul', 'ankara', 'izmir',
        'bursa', 'antalya', 'kocaeli', 'gebze', 'adana', 'konya', 'gaziantep',
        'mersin', 'maslak', 'levent', 'sarıyer', 'ataşehir', 'üsküdar', 'bomonti',
        'mecidiyeköy', 'kadıköy', 'beşiktaş',
    ];
}

function locationBlobFromParts(array $parts): string
{
    return mb_strtolower(json_encode($parts, JSON_UNESCAPED_UNICODE));
}

function classifyLocation(string $blob): string
{
    $blob = mb_strtolower($blob);
    $blob = str_replace(['i̇', 'ı'], 'i', $blob);

    $hasTurkeyKeyword = str_contains($blob, 'turkey')
        || str_contains($blob, 'türkiye')
        || str_contains($blob, 'turkiye');
    $hasIstanbul = str_contains($blob, 'istanbul') || str_contains($blob, 'stanbul');
    $hasOtherTrCity = false;

    foreach (['ankara', 'izmir', 'bursa', 'antalya', 'kocaeli', 'gebze', 'adana'] as $city) {
        if (str_contains($blob, $city)) {
            $hasOtherTrCity = true;
            break;
        }
    }

    $hasRemote = preg_match('/"isremote"\s*:\s*true|"workplacetype"\s*:\s*"remote"/', $blob) === 1
        || (preg_match('/\bremote\b/', preg_replace('/"isremote"\s*:\s*false/', '', $blob) ?? $blob) === 1
            && ($hasTurkeyKeyword || $hasIstanbul || $hasOtherTrCity));

    if ($hasRemote && ($hasTurkeyKeyword || $hasIstanbul || $hasOtherTrCity)) {
        return 'remote_turkey';
    }

    if ($hasIstanbul) {
        return 'istanbul';
    }

    if ($hasTurkeyKeyword || $hasOtherTrCity) {
        return 'turkey';
    }

    if ($hasRemote) {
        return 'global_remote';
    }

    return 'global';
}

function httpGetJson(string $url, array $query = []): array
{
    $startedAt = microtime(true);

    try {
        $response = Illuminate\Support\Facades\Http::timeout(45)
            ->connectTimeout(15)
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => ATS_DISCOVERY_USER_AGENT,
            ])
            ->get($url, $query);
    } catch (Throwable $exception) {
        return [
            'url' => $url,
            'http_status' => null,
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'error' => $exception->getMessage(),
            'json' => null,
            'headers' => [],
            'valid_json' => false,
        ];
    }

    $body = (string) $response->body();
    $json = json_decode($body, true);

    return [
        'url' => $url,
        'http_status' => $response->status(),
        'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        'error' => null,
        'json' => $json,
        'headers' => [
            'content-type' => $response->header('Content-Type'),
            'retry-after' => $response->header('Retry-After'),
            'x-ratelimit-limit' => $response->header('X-RateLimit-Limit'),
            'x-ratelimit-remaining' => $response->header('X-RateLimit-Remaining'),
        ],
        'body_bytes' => strlen($body),
        'valid_json' => is_array($json),
    ];
}

function parseTimestamp(mixed $value): ?int
{
    if (! is_string($value) || trim($value) === '') {
        return null;
    }

    $ts = strtotime($value);

    return $ts === false ? null : $ts;
}

/** @return array{fresh:int,stale:int,newest:?string,oldest:?string} */
function freshnessStats(array $timestamps): array
{
    $fresh = 0;
    $stale = 0;
    $newestTs = null;
    $oldestTs = null;

    foreach ($timestamps as $ts) {
        if ($ts === null) {
            continue;
        }
        $newestTs = $newestTs === null ? $ts : max($newestTs, $ts);
        $oldestTs = $oldestTs === null ? $ts : min($oldestTs, $ts);
        $ageDays = (int) floor((time() - $ts) / 86400);
        if ($ageDays > ATS_MAX_POSTING_AGE_DAYS) {
            $stale++;
        } else {
            $fresh++;
        }
    }

    return [
        'fresh' => $fresh,
        'stale' => $stale,
        'newest' => $newestTs ? date('Y-m-d', $newestTs) : null,
        'oldest' => $oldestTs ? date('Y-m-d', $oldestTs) : null,
    ];
}

/** @param list<array<string,mixed>> $jobs */
function duplicateAnalysis(array $jobs, callable $idFn, callable $urlFn, callable $titleFn, callable $companyFn, callable $locationFn): array
{
    $idMap = [];
    $urlMap = [];
    $titleCompanyMap = [];
    $titleLocationMap = [];

    foreach ($jobs as $job) {
        $id = (string) $idFn($job);
        $url = (string) $urlFn($job);
        $title = mb_strtolower(trim((string) $titleFn($job)));
        $company = mb_strtolower(trim((string) $companyFn($job)));
        $location = mb_strtolower(trim((string) $locationFn($job)));

        if ($id !== '') {
            $idMap[$id] = ($idMap[$id] ?? 0) + 1;
        }
        if ($url !== '') {
            $urlMap[$url] = ($urlMap[$url] ?? 0) + 1;
        }
        if ($title !== '' && $company !== '') {
            $titleCompanyMap["{$title}|{$company}"] = ($titleCompanyMap["{$title}|{$company}"] ?? 0) + 1;
        }
        if ($title !== '' && $location !== '') {
            $titleLocationMap["{$title}|{$location}"] = ($titleLocationMap["{$title}|{$location}"] ?? 0) + 1;
        }
    }

    return [
        'duplicate_ids' => array_keys(array_filter($idMap, static fn (int $c): bool => $c > 1)),
        'duplicate_urls' => array_keys(array_filter($urlMap, static fn (int $c): bool => $c > 1)),
        'duplicate_title_company' => array_keys(array_filter($titleCompanyMap, static fn (int $c): bool => $c > 1)),
        'duplicate_title_location' => array_keys(array_filter($titleLocationMap, static fn (int $c): bool => $c > 1)),
    ];
}

function normalizeCompanyKey(?string $name): string
{
    $normalized = mb_strtolower(trim((string) $name));
    $normalized = preg_replace('/\s+(inc|ltd|llc|a\.?ş\.?|group|games|ai)\.?$/u', '', $normalized) ?? $normalized;

    return trim($normalized);
}

/** @return array{urls:list<string>,title_company:list<string>,companies:list<string>} */
function loadExistingFitCareerIndex(): array
{
    $jobs = App\Models\Job::query()
        ->whereHas('sourceProvider', fn ($q) => $q->whereIn('config->provider', ['lever', 'workable', 'remotive']))
        ->get(['title', 'source_company_name', 'external_url']);

    $urls = [];
    $titleCompany = [];
    $companies = [];

    foreach ($jobs as $job) {
        if (filled($job->external_url)) {
            $urls[] = mb_strtolower(trim($job->external_url));
        }
        $companyKey = normalizeCompanyKey($job->source_company_name);
        if ($companyKey !== '') {
            $companies[$companyKey] = true;
        }
        $titleKey = mb_strtolower(trim($job->title));
        if ($titleKey !== '' && $companyKey !== '') {
            $titleCompany[] = "{$titleKey}|{$companyKey}";
        }
    }

    return [
        'urls' => array_values(array_unique($urls)),
        'title_company' => array_values(array_unique($titleCompany)),
        'companies' => array_keys($companies),
    ];
}

/** @param list<array<string,mixed>> $jobs */
function complementarityForJobs(array $jobs, callable $urlFn, callable $titleFn, callable $companyFn, callable $isTurkeyFn): array
{
    $index = loadExistingFitCareerIndex();
    $urlSet = array_fill_keys($index['urls'], true);
    $titleCompanySet = array_fill_keys($index['title_company'], true);
    $companySet = array_fill_keys($index['companies'], true);

    $turkeyJobs = 0;
    $incrementalTurkey = 0;
    $incrementalIstanbul = 0;
    $newCompanies = [];
    $urlOverlap = 0;
    $titleCompanyOverlap = 0;

    foreach ($jobs as $job) {
        if (! $isTurkeyFn($job)) {
            continue;
        }

        $turkeyJobs++;
        $url = mb_strtolower(trim((string) $urlFn($job)));
        $companyKey = normalizeCompanyKey((string) $companyFn($job));
        $titleKey = mb_strtolower(trim((string) $titleFn($job)));
        $tcKey = "{$titleKey}|{$companyKey}";
        $locationClass = $job['_location_class'] ?? 'global';
        $isIncremental = true;

        if ($url !== '' && isset($urlSet[$url])) {
            $urlOverlap++;
            $isIncremental = false;
        }
        if ($tcKey !== '|' && isset($titleCompanySet[$tcKey])) {
            $titleCompanyOverlap++;
            $isIncremental = false;
        }
        if ($companyKey !== '' && isset($companySet[$companyKey])) {
            // company exists but job may still be incremental
        } elseif ($companyKey !== '') {
            $newCompanies[$companyKey] = (string) $companyFn($job);
        }

        if ($isIncremental) {
            $incrementalTurkey++;
            if ($locationClass === 'istanbul') {
                $incrementalIstanbul++;
            }
        }
    }

    return [
        'existing_fitcareer_jobs' => count($index['urls']),
        'turkey_jobs_evaluated' => $turkeyJobs,
        'url_overlap' => $urlOverlap,
        'title_company_overlap' => $titleCompanyOverlap,
        'incremental_turkey_jobs' => $incrementalTurkey,
        'incremental_istanbul_jobs' => $incrementalIstanbul,
        'new_companies' => array_values($newCompanies),
    ];
}

function categorizeBoard(array $board): string
{
    if (($board['http_status'] ?? 0) !== 200 || ! ($board['valid_json'] ?? false)) {
        return 'C';
    }
    if (($board['total_jobs'] ?? 0) === 0) {
        return 'C';
    }
    if (($board['turkey_jobs'] ?? 0) === 0) {
        return 'C';
    }
    if (($board['fresh_turkey_jobs'] ?? 0) === 0) {
        return 'C';
    }

    $tr = $board['turkey_jobs'] ?? 0;
    $freshTr = $board['fresh_turkey_jobs'] ?? 0;

    if ($tr >= 3 && $freshTr >= 3) {
        return 'A';
    }

    return 'B';
}

function writeCoverageMarkdown(string $path, string $providerTitle, array $report): void
{
    $summary = $report['summary'];
    $lines = [
        "# {$providerTitle} Coverage Discovery",
        '',
        'Generated: '.($report['generated_at'] ?? 'unknown'),
        '',
        '## Summary',
        '',
        '| Metric | Value |',
        '| --- | ---: |',
        '| Candidate boards probed | '.($summary['total_candidates_probed'] ?? 0).' |',
        '| Valid boards (HTTP 200 + jobs) | '.($summary['valid_boards_with_jobs'] ?? 0).' |',
        '| Category A | '.($summary['category_a'] ?? 0).' |',
        '| Category B | '.($summary['category_b'] ?? 0).' |',
        '| Category C | '.($summary['category_c'] ?? 0).' |',
        '| Total jobs (valid boards) | '.($summary['total_jobs_all_valid_boards'] ?? 0).' |',
        '| Total Turkey jobs | '.($summary['total_turkey_jobs'] ?? 0).' |',
        '| Total Istanbul jobs | '.($summary['total_istanbul_jobs'] ?? 0).' |',
        '| Fresh Turkey jobs | '.($summary['total_fresh_turkey_jobs'] ?? 0).' |',
        '| Unique Turkey companies (A+B) | '.($summary['unique_turkey_companies'] ?? 0).' |',
        '',
        '## API / Legal',
        '',
    ];

    foreach ($report['api_legal'] ?? [] as $key => $value) {
        $lines[] = '- **'.str_replace('_', ' ', ucfirst($key)).':** '.$value;
    }

    $lines[] = '';
    $lines[] = '## Top A Boards';
    $lines[] = '';
    $lines[] = '| Company | Board | Total | Turkey | Istanbul | Fresh TR | Stale | Verdict |';
    $lines[] = '| --- | --- | ---: | ---: | ---: | ---: | ---: | --- |';

    foreach ($report['top_a_boards'] ?? [] as $row) {
        $lines[] = sprintf(
            '| %s | %s | %d | %d | %d | %d | %d | %s |',
            $row['company'],
            $row['board'],
            $row['total'],
            $row['turkey'],
            $row['istanbul'],
            $row['fresh_turkey'],
            $row['stale'],
            $row['verdict'],
        );
    }

    $lines[] = '';
    $lines[] = '## Complementarity vs Lever + Workable (205 jobs)';
    $lines[] = '';
    $comp = $report['complementarity'] ?? [];
    $lines[] = '- Incremental Turkey jobs: '.($comp['incremental_turkey_jobs'] ?? 0);
    $lines[] = '- Incremental Istanbul jobs: '.($comp['incremental_istanbul_jobs'] ?? 0);
    $lines[] = '- New companies: '.count($comp['new_companies'] ?? []);
    if (! empty($comp['new_companies'])) {
        $lines[] = '- New company names: '.implode(', ', array_slice($comp['new_companies'], 0, 20));
    }

    $lines[] = '';
    $lines[] = '## Decision';
    $lines[] = '';
    $lines[] = ($report['decision']['label'] ?? 'HOLD').' — '.($report['decision']['rationale'] ?? '');

    file_put_contents($path, implode("\n", $lines)."\n");
}
