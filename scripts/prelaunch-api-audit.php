<?php

declare(strict_types=1);

/**
 * Pre-launch API gate: auth, isolation, draft privacy, employer/candidate lifecycle extras.
 */

$baseUrl = getenv('SMOKE_API_BASE') ?: 'http://localhost/fitcareer/public/api/v1';
$stamp = date('YmdHis');
$password = 'ReleaseQa123!';
$newPassword = 'ReleaseQa456!';
$candidateEmail = "pl-candidate-{$stamp}@fitcareer.test";
$candidateBEmail = "pl-candidate-b-{$stamp}@fitcareer.test";
$companyEmail = "pl-company-{$stamp}@fitcareer.test";
$foreignEmail = "pl-foreign-{$stamp}@fitcareer.test";
$report = ['steps' => [], 'errors' => [], 'base' => $baseUrl];

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
    'name' => "PL Candidate {$stamp}",
    'email' => $candidateEmail,
    'password' => $password,
    'password_confirmation' => $password,
    'role' => 'candidate',
]);
$candidateToken = $candidateReg['body']['data']['token'] ?? null;
mark($report, 'candidate_register', $candidateReg['status'] === 201 && is_string($candidateToken), (string) $candidateReg['status']);

$candidateBReg = api('POST', "$baseUrl/auth/register", [
    'name' => "PL Candidate B {$stamp}",
    'email' => $candidateBEmail,
    'password' => $password,
    'password_confirmation' => $password,
    'role' => 'candidate',
]);
$candidateBToken = $candidateBReg['body']['data']['token'] ?? null;
mark($report, 'candidate_b_register', $candidateBReg['status'] === 201 && is_string($candidateBToken), (string) $candidateBReg['status']);

$companyReg = api('POST', "$baseUrl/auth/register", [
    'name' => "PL Company {$stamp}",
    'email' => $companyEmail,
    'password' => $password,
    'password_confirmation' => $password,
    'role' => 'company',
    'company_name' => "PL Employer {$stamp}",
]);
$companyToken = $companyReg['body']['data']['token'] ?? null;
mark($report, 'company_register', $companyReg['status'] === 201 && is_string($companyToken), (string) $companyReg['status']);

$foreignReg = api('POST', "$baseUrl/auth/register", [
    'name' => "PL Foreign {$stamp}",
    'email' => $foreignEmail,
    'password' => $password,
    'password_confirmation' => $password,
    'role' => 'company',
    'company_name' => "PL Foreign {$stamp}",
]);
if ($foreignReg['status'] === 429) {
    sleep(21);
    $foreignReg = api('POST', "$baseUrl/auth/register", [
        'name' => "PL Foreign {$stamp}",
        'email' => $foreignEmail,
        'password' => $password,
        'password_confirmation' => $password,
        'role' => 'company',
        'company_name' => "PL Foreign {$stamp}",
    ]);
}
$foreignToken = $foreignReg['body']['data']['token'] ?? null;
mark($report, 'foreign_company_register', $foreignReg['status'] === 201 && is_string($foreignToken), (string) $foreignReg['status']);

$crossRoleCompany = api('GET', "$baseUrl/company/profile", null, $candidateToken);
mark($report, 'candidate_blocked_from_company_api', $crossRoleCompany['status'] === 403, (string) $crossRoleCompany['status']);

$crossRoleCandidate = api('GET', "$baseUrl/candidate/profile", null, $companyToken);
mark($report, 'company_blocked_from_candidate_api', $crossRoleCandidate['status'] === 403, (string) $crossRoleCandidate['status']);

$profile = api('GET', "$baseUrl/company/profile", null, $companyToken);
$verification = $profile['body']['data']['verification_status'] ?? null;
$isVerified = $profile['body']['data']['is_verified'] ?? null;
$companyId = $profile['body']['data']['id'] ?? null;
mark($report, 'unverified_not_pending', $verification === 'unverified' && $isVerified === false, json_encode([$verification, $isVerified]));

$verifyReq = api('POST', "$baseUrl/company/verification/request", [], $companyToken);
mark(
    $report,
    'verification_request_pending',
    $verifyReq['status'] === 200
        && ($verifyReq['body']['data']['verification_status'] ?? null) === 'pending'
        && ($verifyReq['body']['data']['is_verified'] ?? true) === false,
    (string) $verifyReq['status']
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
exec('php artisan company:verification approve '.escapeshellarg((string) $companyId).' 2>&1', $invalidApprove, $invalidCode);
mark($report, 'verification_invalid_reapprove_blocked', $invalidCode !== 0);

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
mark($report, 'draft_created', $draft['status'] === 201 && is_int($jobId), (string) $draft['status']);

$draftPublic = api('GET', "$baseUrl/jobs/{$jobSlug}");
mark($report, 'draft_not_public', $draftPublic['status'] === 404, (string) $draftPublic['status']);

$foreignEdit = api('PUT', "$baseUrl/company/jobs/{$jobId}", ['title' => 'Hijacked'], $foreignToken);
mark($report, 'foreign_company_cannot_edit_job', in_array($foreignEdit['status'], [403, 404], true), (string) $foreignEdit['status']);

$foreignPublish = api('POST', "$baseUrl/company/jobs/{$jobId}/publish", [], $foreignToken);
mark($report, 'foreign_company_cannot_publish_job', in_array($foreignPublish['status'], [403, 404], true), (string) $foreignPublish['status']);

$publish = api('POST', "$baseUrl/company/jobs/{$jobId}/publish", [], $companyToken);
mark($report, 'job_publish', $publish['status'] === 200 && ($publish['body']['data']['status'] ?? null) === 'published', (string) $publish['status']);

$publicJob = api('GET', "$baseUrl/jobs/{$jobSlug}");
mark(
    $report,
    'published_job_public',
    $publicJob['status'] === 200
        && ($publicJob['body']['data']['company']['is_verified'] ?? false) === true,
    (string) $publicJob['status']
);

$search = api('GET', "$baseUrl/jobs?keyword=QA", null, $candidateToken);
mark($report, 'search_qa_synonym', $search['status'] === 200, (string) $search['status']);

$frontendSearch = api('GET', "$baseUrl/jobs?keyword=frontend", null, $candidateToken);
mark($report, 'search_frontend_synonym', $frontendSearch['status'] === 200, (string) $frontendSearch['status']);

$istanbul = api('GET', "$baseUrl/jobs?location=".rawurlencode('İstanbul'), null, $candidateToken);
mark($report, 'search_turkish_location', $istanbul['status'] === 200, (string) $istanbul['status']);

$apply = api('POST', "$baseUrl/candidate/applications", [
    'job_id' => $jobId,
    'cover_letter' => 'I would like to join as a junior backend developer.',
], $candidateToken);
$applicationId = $apply['body']['data']['id'] ?? null;
$matchScore = $apply['body']['data']['match_score'] ?? 'missing';
mark($report, 'candidate_apply', $apply['status'] === 201 && is_int($applicationId), (string) $apply['status']);
$report['match_score_snapshot'] = $matchScore;

$dup = api('POST', "$baseUrl/candidate/applications", ['job_id' => $jobId], $candidateToken);
mark($report, 'duplicate_application_rejected', $dup['status'] === 422, (string) $dup['status']);

$foreignApp = api('GET', "$baseUrl/candidate/applications/{$applicationId}", null, $candidateBToken);
mark($report, 'candidate_b_cannot_read_candidate_a_application', $foreignApp['status'] === 404, (string) $foreignApp['status']);

$foreignCompanyApp = api('GET', "$baseUrl/company/applications/{$applicationId}", null, $foreignToken);
mark($report, 'foreign_company_cannot_read_application', in_array($foreignCompanyApp['status'], [403, 404], true), (string) $foreignCompanyApp['status']);

$ownApp = api('GET', "$baseUrl/company/applications/{$applicationId}", null, $companyToken);
$matchStatus = $ownApp['body']['data']['match_analysis_status'] ?? null;
$hasMatchKey = is_array($ownApp['body']['data'] ?? null) && array_key_exists('match_score', $ownApp['body']['data']);
$exposedScore = $hasMatchKey ? $ownApp['body']['data']['match_score'] : 'missing_key';
mark($report, 'company_sees_own_application', $ownApp['status'] === 200, (string) $ownApp['status']);
mark($report, 'match_score_key_present', $hasMatchKey, json_encode([$exposedScore, $matchStatus]));
mark(
    $report,
    'match_score_not_fabricated_zero_when_null',
    ! $hasMatchKey || $exposedScore !== 0 || ($apply['body']['data']['match_score'] ?? null) === 0,
    json_encode($exposedScore)
);

$status = api('PATCH', "$baseUrl/company/applications/{$applicationId}/status", [
    'status' => 'under_review',
    'note' => 'Prelaunch review.',
], $companyToken);
mark($report, 'application_status_update', $status['status'] === 200 && ($status['body']['data']['status'] ?? null) === 'under_review', (string) $status['status']);

$history = $status['body']['data']['status_history'] ?? [];
mark($report, 'application_history_present', is_array($history) && count($history) >= 2, (string) count($history));

$notes = api('GET', "$baseUrl/candidate/notifications", null, $candidateToken);
mark($report, 'candidate_notification_after_status', $notes['status'] === 200, (string) $notes['status']);

$apps = api('GET', "$baseUrl/company/applications?sort=attention", null, $companyToken);
$items = $apps['body']['data']['items'] ?? [];
mark($report, 'company_application_list', $apps['status'] === 200 && count($items) >= 1, (string) $apps['status']);
mark($report, 'list_omits_match_details', ! isset($items[0]['match_details']), json_encode(array_keys($items[0] ?? [])));

$pw = api('PUT', "$baseUrl/auth/password", [
    'current_password' => $password,
    'password' => $newPassword,
    'password_confirmation' => $newPassword,
], $candidateToken);
mark($report, 'candidate_password_update', $pw['status'] === 200, (string) $pw['status']);

$oldLogin = api('POST', "$baseUrl/auth/login", [
    'email' => $candidateEmail,
    'password' => $password,
]);
mark($report, 'old_password_rejected', $oldLogin['status'] === 422 || $oldLogin['status'] === 401, (string) $oldLogin['status']);

$newLogin = api('POST', "$baseUrl/auth/login", [
    'email' => $candidateEmail,
    'password' => $newPassword,
]);
$newToken = $newLogin['body']['data']['token'] ?? null;
mark($report, 'new_password_login', $newLogin['status'] === 200 && is_string($newToken), (string) $newLogin['status']);

$afterPwProtected = api('GET', "$baseUrl/candidate/profile", null, $candidateToken);
mark($report, 'old_token_revoked_after_password_change', $afterPwProtected['status'] === 401, (string) $afterPwProtected['status']);

$logout = api('POST', "$baseUrl/auth/logout", [], $companyToken);
$afterLogout = api('GET', "$baseUrl/company/profile", null, $companyToken);
mark($report, 'company_logout', $logout['status'] === 200, (string) $logout['status']);
mark($report, 'company_protected_after_logout', $afterLogout['status'] === 401, (string) $afterLogout['status']);

$guest401 = api('GET', "$baseUrl/company/applications");
mark($report, 'guest_company_applications_401', $guest401['status'] === 401, (string) $guest401['status']);

$report['actors'] = [
    'candidate' => $candidateEmail,
    'candidate_b' => $candidateBEmail,
    'company' => $companyEmail,
    'foreign_company' => $foreignEmail,
    'company_id' => $companyId,
    'job_slug' => $jobSlug,
    'application_id' => $applicationId,
];
$report['failed_count'] = count($report['errors']);
$report['verdict'] = $report['failed_count'] === 0 ? 'PASS' : 'FAIL';

$dir = __DIR__.'/../storage/smoke-test';
if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}
file_put_contents($dir.'/prelaunch-api-audit.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($report['failed_count'] === 0 ? 0 : 1);
