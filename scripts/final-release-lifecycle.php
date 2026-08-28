<?php

declare(strict_types=1);

/**
 * Final release candidate API lifecycle smoke.
 * Creates ephemeral candidate + company actors against the live app DB.
 */

$baseUrl = getenv('SMOKE_API_BASE') ?: 'http://127.0.0.1:8000/api/v1';
$stamp = date('YmdHis');
$password = 'ReleaseQa123!';
$candidateEmail = "rc-candidate-{$stamp}@fitcareer.test";
$companyEmail = "rc-company-{$stamp}@fitcareer.test";
$foreignEmail = "rc-foreign-{$stamp}@fitcareer.test";
$report = ['steps' => [], 'errors' => []];

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
        CURLOPT_TIMEOUT => 45,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'status' => $status,
        'body' => json_decode($raw ?: 'null', true),
        'error' => $error ?: null,
        'raw' => $raw,
    ];
}

function mark(array &$report, string $key, bool $ok, string $detail = ''): void
{
    $report['steps'][$key] = $ok ? 'PASS' : 'FAIL';
    if (! $ok) {
        $report['errors'][] = $key.($detail !== '' ? ': '.$detail : '');
    }
}

$candidateReg = api('POST', "$baseUrl/auth/register", [
    'name' => "RC Candidate {$stamp}",
    'email' => $candidateEmail,
    'password' => $password,
    'password_confirmation' => $password,
    'role' => 'candidate',
]);
$candidateToken = $candidateReg['body']['data']['token'] ?? null;
mark($report, 'candidate_register', $candidateReg['status'] === 201 && is_string($candidateToken), (string) $candidateReg['status']);

$companyReg = api('POST', "$baseUrl/auth/register", [
    'name' => "RC Company {$stamp}",
    'email' => $companyEmail,
    'password' => $password,
    'password_confirmation' => $password,
    'role' => 'company',
    'company_name' => "RC Employer {$stamp}",
]);
$companyToken = $companyReg['body']['data']['token'] ?? null;
mark($report, 'company_register', $companyReg['status'] === 201 && is_string($companyToken), (string) $companyReg['status']);

$foreignReg = api('POST', "$baseUrl/auth/register", [
    'name' => "RC Foreign {$stamp}",
    'email' => $foreignEmail,
    'password' => $password,
    'password_confirmation' => $password,
    'role' => 'company',
    'company_name' => "RC Foreign {$stamp}",
]);
$foreignToken = $foreignReg['body']['data']['token'] ?? null;
mark($report, 'foreign_company_register', $foreignReg['status'] === 201 && is_string($foreignToken), (string) $foreignReg['status']);

$profile = api('GET', "$baseUrl/company/profile", null, $companyToken);
$verification = $profile['body']['data']['verification_status'] ?? null;
$isVerified = $profile['body']['data']['is_verified'] ?? null;
$companyId = $profile['body']['data']['id'] ?? null;
$companySlug = $profile['body']['data']['slug'] ?? null;
mark($report, 'company_unverified_not_pending', $verification === 'unverified' && $isVerified === false, json_encode([$verification, $isVerified]));

$profileUpdate = api('PUT', "$baseUrl/company/profile", [
    'city' => 'Istanbul',
    'country' => 'Turkey',
    'industry' => 'Software',
    'description' => 'Ephemeral release-candidate employer.',
], $companyToken);
mark($report, 'company_profile_update', $profileUpdate['status'] === 200, (string) $profileUpdate['status']);

$pending = api('POST', "$baseUrl/company/verification/request", [], $companyToken);
mark(
    $report,
    'verification_request_pending',
    $pending['status'] === 200
        && ($pending['body']['data']['verification_status'] ?? null) === 'pending'
        && ($pending['body']['data']['is_verified'] ?? true) === false,
    (string) $pending['status']
);

$approve = [];
exec('php artisan company:verification approve '.escapeshellarg((string) $companyId).' 2>&1', $approve, $approveCode);
mark($report, 'verification_artisan_approve', $approveCode === 0, implode("\n", $approve));

$verified = api('GET', "$baseUrl/company/profile", null, $companyToken);
mark(
    $report,
    'verification_verified_consistent',
    ($verified['body']['data']['verification_status'] ?? null) === 'verified'
        && ($verified['body']['data']['is_verified'] ?? false) === true,
    json_encode($verified['body']['data'] ?? [])
);

$invalidApprove = [];
exec('php artisan company:verification approve '.escapeshellarg((string) $companyId).' 2>&1', $invalidApprove, $invalidApproveCode);
mark($report, 'verification_invalid_transition_blocked', $invalidApproveCode !== 0);

$missingCompany = [];
exec('php artisan company:verification approve missing-rc-company 2>&1', $missingCompany, $missingCode);
mark($report, 'verification_missing_company_blocked', $missingCode !== 0);

$candidateCannotCompany = api('GET', "$baseUrl/company/profile", null, $candidateToken);
mark($report, 'candidate_blocked_from_company_profile', $candidateCannotCompany['status'] === 403, (string) $candidateCannotCompany['status']);

$companyCannotCandidate = api('GET', "$baseUrl/candidate/profile", null, $companyToken);
mark($report, 'company_blocked_from_candidate_profile', $companyCannotCandidate['status'] === 403, (string) $companyCannotCandidate['status']);

$jobDescription = str_repeat('Junior backend developer role in Istanbul with mentoring, Laravel APIs, and production delivery. ', 2);
$shortJob = api('POST', "$baseUrl/company/jobs", [
    'title' => 'Junior Backend Developer',
    'description' => 'x',
    'employment_type' => 'full_time',
    'work_type' => 'onsite',
    'city' => 'Istanbul',
    'country' => 'Turkey',
    'experience_level' => 'entry',
], $companyToken);
mark($report, 'invalid_short_description_rejected', $shortJob['status'] === 422, (string) $shortJob['status']);

$onsiteNoCity = api('POST', "$baseUrl/company/jobs", [
    'title' => 'Junior Backend Developer',
    'description' => $jobDescription,
    'employment_type' => 'full_time',
    'work_type' => 'onsite',
    'country' => 'Turkey',
    'experience_level' => 'entry',
], $companyToken);
mark($report, 'onsite_without_city_rejected', $onsiteNoCity['status'] === 422, (string) $onsiteNoCity['status']);

$draft = api('POST', "$baseUrl/company/jobs", [
    'title' => 'Junior Backend Developer',
    'description' => $jobDescription,
    'employment_type' => 'full_time',
    'work_type' => 'onsite',
    'experience_level' => 'entry',
    'city' => 'Istanbul',
    'country' => 'Turkey',
], $companyToken);
$jobId = $draft['body']['data']['id'] ?? null;
$jobSlug = $draft['body']['data']['slug'] ?? null;
mark(
    $report,
    'junior_istanbul_job_created',
    $draft['status'] === 201
        && ($draft['body']['data']['experience_level'] ?? null) === 'entry'
        && ($draft['body']['data']['work_type'] ?? null) === 'onsite'
        && ($draft['body']['data']['city'] ?? null) === 'Istanbul'
        && ($draft['body']['data']['source'] ?? null) === 'internal',
    json_encode($draft['body']['data'] ?? [])
);

$draftPublic = api('GET', "$baseUrl/jobs/{$jobSlug}");
mark($report, 'draft_not_public', $draftPublic['status'] === 404, (string) $draftPublic['status']);

$foreignEdit = api('PUT', "$baseUrl/company/jobs/{$jobId}", ['title' => 'Hijacked'], $foreignToken);
mark($report, 'foreign_company_cannot_edit_job', in_array($foreignEdit['status'], [403, 404], true), (string) $foreignEdit['status']);

$publish = api('POST', "$baseUrl/company/jobs/{$jobId}/publish", [], $companyToken);
mark($report, 'job_publish', $publish['status'] === 200 && ($publish['body']['data']['status'] ?? null) === 'published', (string) $publish['status']);

$publicJob = api('GET', "$baseUrl/jobs/{$jobSlug}");
mark(
    $report,
    'published_job_public_labels',
    $publicJob['status'] === 200
        && ($publicJob['body']['data']['source'] ?? null) === 'internal'
        && ($publicJob['body']['data']['company']['is_verified'] ?? false) === true,
    json_encode($publicJob['body']['data']['company'] ?? [])
);

$qaSearch = api('GET', "$baseUrl/jobs?keyword=QA", null, $candidateToken);
mark($report, 'search_keyword', $qaSearch['status'] === 200, (string) $qaSearch['status']);

$istanbulSearch = api('GET', "$baseUrl/jobs?location=".rawurlencode('İstanbul'), null, $candidateToken);
$istanbulAscii = api('GET', "$baseUrl/jobs?location=".rawurlencode('Istanbul'), null, $candidateToken);
mark(
    $report,
    'search_turkish_location',
    $istanbulSearch['status'] === 200
        && $istanbulAscii['status'] === 200
        && ($istanbulSearch['body']['data']['pagination']['total'] ?? -1) === ($istanbulAscii['body']['data']['pagination']['total'] ?? -2),
    json_encode([
        $istanbulSearch['body']['data']['pagination']['total'] ?? null,
        $istanbulAscii['body']['data']['pagination']['total'] ?? null,
    ])
);

$frontendSearch = api('GET', "$baseUrl/jobs?keyword=frontend", null, $candidateToken);
mark($report, 'search_frontend_synonym', $frontendSearch['status'] === 200, (string) $frontendSearch['status']);

$filter = api('GET', "$baseUrl/jobs?experience_level=entry&work_type=onsite&min_trust_score=1", null, $candidateToken);
mark($report, 'search_filters', $filter['status'] === 200, (string) $filter['status']);

$save = api('POST', "$baseUrl/candidate/saved-jobs/{$jobId}", [], $candidateToken);
$savedIds = api('GET', "$baseUrl/candidate/saved-jobs/ids", null, $candidateToken);
$unsave = api('DELETE', "$baseUrl/candidate/saved-jobs/{$jobId}", null, $candidateToken);
mark($report, 'save_job', $save['status'] === 201, (string) $save['status']);
mark($report, 'saved_ids_contains', in_array($jobId, $savedIds['body']['data']['job_ids'] ?? [], true));
mark($report, 'unsave_job', $unsave['status'] === 200, (string) $unsave['status']);

$apply = api('POST', "$baseUrl/candidate/applications", [
    'job_id' => $jobId,
    'cover_letter' => 'I would like to join as a junior backend developer.',
], $candidateToken);
$applicationId = $apply['body']['data']['id'] ?? null;
mark($report, 'candidate_apply', $apply['status'] === 201 && is_int($applicationId), (string) $apply['status']);

$foreignStatus = api('PATCH', "$baseUrl/company/applications/{$applicationId}/status", [
    'status' => 'under_review',
], $foreignToken);
mark($report, 'foreign_company_cannot_update_application', in_array($foreignStatus['status'], [403, 404], true), (string) $foreignStatus['status']);

$status = api('PATCH', "$baseUrl/company/applications/{$applicationId}/status", [
    'status' => 'under_review',
    'note' => 'Release candidate review.',
], $companyToken);
mark($report, 'application_status_update', $status['status'] === 200 && ($status['body']['data']['status'] ?? null) === 'under_review', (string) $status['status']);

$notifications = api('GET', "$baseUrl/candidate/notifications", null, $candidateToken);
$items = $notifications['body']['data']['items'] ?? [];
mark($report, 'candidate_notification_after_status', $notifications['status'] === 200 && count($items) > 0, (string) $notifications['status']);

$dashboard = api('GET', "$baseUrl/candidate/dashboard", null, $candidateToken);
mark($report, 'candidate_dashboard', $dashboard['status'] === 200, (string) $dashboard['status']);

$candidateProfile = api('GET', "$baseUrl/candidate/profile", null, $candidateToken);
mark($report, 'candidate_profile', $candidateProfile['status'] === 200, (string) $candidateProfile['status']);

$logout = api('POST', "$baseUrl/auth/logout", [], $companyToken);
$afterLogout = api('GET', "$baseUrl/company/profile", null, $companyToken);
mark($report, 'company_logout', $logout['status'] === 200, (string) $logout['status']);
mark($report, 'company_protected_after_logout', $afterLogout['status'] === 401, (string) $afterLogout['status']);

$report['actors'] = [
    'candidate' => $candidateEmail,
    'company' => $companyEmail,
    'foreign_company' => $foreignEmail,
    'company_id' => $companyId,
    'company_slug' => $companySlug,
    'job_slug' => $jobSlug,
];
$report['failed_count'] = count($report['errors']);
$report['verdict'] = $report['failed_count'] === 0 ? 'PASS' : 'FAIL';

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($report['failed_count'] === 0 ? 0 : 1);
