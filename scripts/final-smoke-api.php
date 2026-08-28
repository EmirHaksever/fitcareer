<?php

declare(strict_types=1);

/**
 * API + DB consistency smoke test (no PII dump).
 * Creates ephemeral candidate via register, runs flows, outputs JSON report.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\JobStatus;
use App\Enums\TrustLabel;
use App\Models\Job;

$baseUrl = getenv('SMOKE_API_BASE') ?: 'http://127.0.0.1:8000/api/v1';
$password = 'SmokeTest123!';
$email = 'smoke-'.date('YmdHis').'@example.test';

function api(string $method, string $url, ?array $body = null, ?string $token = null): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer '.$token;
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => json_decode($raw ?: 'null', true)];
}

$report = ['steps' => [], 'db' => [], 'errors' => []];

$dbQuery = Job::query()
    ->where('status', JobStatus::Published)
    ->where(function ($q): void {
        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
    });

$report['db'] = [
    'total_jobs' => (clone $dbQuery)->count(),
    'trusted_jobs' => (clone $dbQuery)->where('trust_label', TrustLabel::Verified)->count(),
    'suspicious_jobs' => (clone $dbQuery)->whereIn('trust_label', [TrustLabel::Suspicious, TrustLabel::LowTrust])->count(),
];

// Register
$reg = api('POST', "$baseUrl/auth/register", [
    'name' => 'Smoke Tester',
    'email' => $email,
    'password' => $password,
    'password_confirmation' => $password,
    'role' => 'candidate',
]);
$report['steps']['register'] = $reg['status'] === 201 ? 'PASS' : 'FAIL';
if ($reg['status'] !== 201) {
    $report['errors'][] = 'register failed: '.$reg['status'];
    echo json_encode($report, JSON_PRETTY_PRINT).PHP_EOL;
    exit(1);
}

$token = $reg['body']['data']['token'] ?? null;

// Wrong login
$badLogin = api('POST', "$baseUrl/auth/login", [
    'email' => $email,
    'password' => 'WrongPassword123!',
]);
$report['steps']['login_wrong_password'] = $badLogin['status'] === 422 ? 'PASS' : 'FAIL';

// Good login
$login = api('POST', "$baseUrl/auth/login", [
    'email' => $email,
    'password' => $password,
]);
$report['steps']['login'] = $login['status'] === 200 ? 'PASS' : 'FAIL';
$token = $login['body']['data']['token'] ?? $token;

// Dashboard
$dash = api('GET', "$baseUrl/candidate/dashboard", null, $token);
$report['steps']['dashboard'] = $dash['status'] === 200 ? 'PASS' : 'FAIL';
if ($dash['status'] === 200) {
    $stats = $dash['body']['data']['stats'] ?? [];
    $report['dashboard_api'] = $stats;
    $report['steps']['dashboard_total_jobs_match'] =
        ($stats['total_jobs'] ?? -1) === $report['db']['total_jobs'] ? 'PASS' : 'FAIL';
    $report['steps']['dashboard_trusted_match'] =
        ($stats['trusted_jobs'] ?? -1) === $report['db']['trusted_jobs'] ? 'PASS' : 'FAIL';
    $report['steps']['dashboard_suspicious_match'] =
        ($stats['suspicious_jobs'] ?? -1) === $report['db']['suspicious_jobs'] ? 'PASS' : 'FAIL';
    $report['steps']['dashboard_avg_fit_null'] =
        ($stats['average_fit_score'] ?? 'x') === null ? 'PASS' : 'FAIL';
}

// Jobs list
$jobs = api('GET', "$baseUrl/jobs?per_page=5", null, $token);
$report['steps']['jobs_list'] = $jobs['status'] === 200 && ! empty($jobs['body']['data']['items']) ? 'PASS' : 'FAIL';

$firstJob = $jobs['body']['data']['items'][0] ?? null;
$turkishJob = null;
$englishJob = null;

foreach ($jobs['body']['data']['items'] ?? [] as $item) {
    $detail = api('GET', "$baseUrl/jobs/{$item['slug']}", null, $token);
    if ($detail['status'] !== 200) {
        continue;
    }
    $desc = $detail['body']['data']['description'] ?? '';
    if (preg_match('/[ğüşıöçĞÜŞİÖÇ]/u', $desc) && $turkishJob === null) {
        $turkishJob = ['slug' => $item['slug'], 'title' => $item['title']];
    }
    if (! preg_match('/[ğüşıöçĞÜŞİÖÇ]/u', $desc)
        && preg_match('/\b(the|and|you|our|will)\b/i', $desc)
        && $englishJob === null) {
        $englishJob = ['slug' => $item['slug'], 'title' => $item['title']];
    }
}

$report['samples'] = ['turkish' => $turkishJob, 'english' => $englishJob];

if ($firstJob) {
    $detail = api('GET', "$baseUrl/jobs/{$firstJob['slug']}", null, $token);
    $report['steps']['job_detail'] = $detail['status'] === 200 ? 'PASS' : 'FAIL';
}

// Save / unsave
if ($firstJob) {
    $save = api('POST', "$baseUrl/candidate/saved-jobs/{$firstJob['id']}", [], $token);
    $report['steps']['save_job'] = $save['status'] === 201 ? 'PASS' : 'FAIL';
    $saved = api('GET', "$baseUrl/candidate/saved-jobs/ids", null, $token);
    $report['steps']['saved_ids_contains'] = in_array($firstJob['id'], $saved['body']['data']['job_ids'] ?? [], true) ? 'PASS' : 'FAIL';
    $unsave = api('DELETE', "$baseUrl/candidate/saved-jobs/{$firstJob['id']}", null, $token);
    $report['steps']['unsave_job'] = $unsave['status'] === 200 ? 'PASS' : 'FAIL';
}

// Saved list
$savedList = api('GET', "$baseUrl/candidate/saved-jobs", null, $token);
$report['steps']['saved_list'] = $savedList['status'] === 200 ? 'PASS' : 'FAIL';

// Fit analysis fields
$report['steps']['analyzed_jobs_array'] =
    isset($dash['body']['data']['analyzed_jobs']) && is_array($dash['body']['data']['analyzed_jobs']) ? 'PASS' : 'FAIL';

// Password wrong current
$badPw = api('PUT', "$baseUrl/auth/password", [
    'current_password' => 'Wrong!',
    'password' => 'NewSmoke123!',
    'password_confirmation' => 'NewSmoke123!',
], $token);
$report['steps']['password_wrong_current'] =
    $badPw['status'] === 422 && isset($badPw['body']['errors']['current_password']) ? 'PASS' : 'FAIL';

// Password success
$newPassword = 'NewSmoke456!';
$goodPw = api('PUT', "$baseUrl/auth/password", [
    'current_password' => $password,
    'password' => $newPassword,
    'password_confirmation' => $newPassword,
], $token);
$report['steps']['password_update'] = $goodPw['status'] === 200 ? 'PASS' : 'FAIL';

// Old token should fail
$afterPw = api('GET', "$baseUrl/candidate/dashboard", null, $token);
$report['steps']['token_revoked_after_password'] = $afterPw['status'] === 401 ? 'PASS' : 'FAIL';

// Re-login
$relogin = api('POST', "$baseUrl/auth/login", [
    'email' => $email,
    'password' => $newPassword,
]);
$report['steps']['relogin_after_password'] = $relogin['status'] === 200 ? 'PASS' : 'FAIL';

// Logout
$newToken = $relogin['body']['data']['token'] ?? null;
if ($newToken) {
    $logout = api('POST', "$baseUrl/auth/logout", [], $newToken);
    $report['steps']['logout'] = $logout['status'] === 200 ? 'PASS' : 'FAIL';
    $afterLogout = api('GET', "$baseUrl/candidate/dashboard", null, $newToken);
    $report['steps']['token_invalid_after_logout'] = $afterLogout['status'] === 401 ? 'PASS' : 'FAIL';
}

$report['ephemeral_user'] = $email;
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
