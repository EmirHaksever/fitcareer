<?php

declare(strict_types=1);

/**
 * Notifications V1 full lifecycle QA — ephemeral users, real HTTP + DB verification.
 * Usage: php scripts/notification-lifecycle-qa.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Application;
use App\Models\Notification;
use App\Models\User;

$baseUrl = getenv('SMOKE_API_BASE') ?: 'http://127.0.0.1:8000/api/v1';
$password = 'SmokeTest123!';
$suffix = (string) time();

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
        CURLOPT_TIMEOUT => 60,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => json_decode($raw ?: 'null', true), 'raw' => $raw];
}

function pass(array &$report, string $name, bool $ok, array $details = []): void
{
    $report['scenarios'][$name] = array_merge(['result' => $ok ? 'PASS' : 'FAIL'], $details);
}

$report = [
    'executed_at' => date('c'),
    'api_base' => $baseUrl,
    'scenarios' => [],
    'dedupe_analysis' => [],
    'errors' => [],
];

// --- Setup: Company + Candidate A + Candidate B ---
$companyReg = api('POST', "$baseUrl/auth/register", [
    'name' => 'Lifecycle QA Company',
    'email' => "lifecycle-co-{$suffix}@example.test",
    'password' => $password,
    'password_confirmation' => $password,
    'role' => 'company',
    'company_name' => 'Lifecycle QA Corp',
]);
if ($companyReg['status'] !== 201) {
    $report['errors'][] = 'company register failed: HTTP '.$companyReg['status'].' body: '.substr((string) ($companyReg['raw'] ?? ''), 0, 500);
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
    exit(1);
}
$companyToken = $companyReg['body']['data']['token'] ?? null;

$candidateAReg = api('POST', "$baseUrl/auth/register", [
    'name' => 'Lifecycle Candidate A',
    'email' => "lifecycle-a-{$suffix}@example.test",
    'password' => $password,
    'password_confirmation' => $password,
    'role' => 'candidate',
]);
$candidateBReg = api('POST', "$baseUrl/auth/register", [
    'name' => 'Lifecycle Candidate B',
    'email' => "lifecycle-b-{$suffix}@example.test",
    'password' => $password,
    'password_confirmation' => $password,
    'role' => 'candidate',
]);
$candidateAToken = $candidateAReg['body']['data']['token'] ?? null;
$candidateBToken = $candidateBReg['body']['data']['token'] ?? null;
$candidateAUserId = (int) ($candidateAReg['body']['data']['user']['id'] ?? 0);
$candidateBUserId = (int) ($candidateBReg['body']['data']['user']['id'] ?? 0);

$jobCreate = api('POST', "$baseUrl/company/jobs", [
    'title' => 'Lifecycle QA Backend Developer',
    'description' => str_repeat('Build reliable Laravel APIs for production systems. ', 4),
    'employment_type' => 'full_time',
    'work_type' => 'remote',
    'category' => 'engineering',
    'city' => 'Istanbul',
    'country' => 'Turkey',
    'contact_email' => 'jobs@example.test',
], $companyToken);
$jobId = (int) ($jobCreate['body']['data']['id'] ?? 0);
api('POST', "$baseUrl/company/jobs/{$jobId}/publish", null, $companyToken);

$apply = api('POST', "$baseUrl/candidate/applications", ['job_id' => $jobId], $candidateAToken);
$applicationId = (int) ($apply['body']['data']['id'] ?? 0);

$application = Application::query()->find($applicationId);
$initialStatus = $application?->status->value ?? 'unknown';

$countNotificationsForA = fn (): int => Notification::query()
    ->where('notifiable_type', User::class)
    ->where('notifiable_id', $candidateAUserId)
    ->count();

$unreadViaApi = fn (?string $token): int => (int) (api('GET', "$baseUrl/candidate/notifications/unread-count", null, $token)['body']['data']['unread_count'] ?? -1);

// --- SCENARIO 1: Initial notification ---
$beforeCount = $countNotificationsForA();
$beforeUnread = $unreadViaApi($candidateAToken);

$transition1 = api('PATCH', "$baseUrl/company/applications/{$applicationId}/status", [
    'status' => 'under_review',
], $companyToken);

$afterCount = $countNotificationsForA();
$afterUnread = $unreadViaApi($candidateAToken);
$list1 = api('GET', "$baseUrl/candidate/notifications", null, $candidateAToken);
$firstNotificationId = $list1['body']['data']['items'][0]['id'] ?? null;
$dbNotification = Notification::query()->whereKey($firstNotificationId)->first();

pass($report, 'scenario_1_initial_notification', (
    $transition1['status'] === 200
    && $afterCount === $beforeCount + 1
    && $afterUnread === $beforeUnread + 1
    && count($list1['body']['data']['items'] ?? []) === 1
    && ($list1['body']['data']['items'][0]['title'] ?? '') === 'Başvuru durumu güncellendi'
    && ($dbNotification?->notifiable_id ?? 0) === $candidateAUserId
    && str_contains((string) ($list1['body']['data']['items'][0]['body'] ?? ''), 'Lifecycle QA Backend Developer')
), [
    'initial_status' => $initialStatus,
    'transition_status' => $transition1['status'],
    'db_count_before' => $beforeCount,
    'db_count_after' => $afterCount,
    'unread_before' => $beforeUnread,
    'unread_after' => $afterUnread,
    'notification_id' => $firstNotificationId,
    'dedupe_key' => $dbNotification?->data['dedupe_key'] ?? null,
]);

// --- SCENARIO 2: Same status repeated ---
$countBeforeRepeat = $countNotificationsForA();
$unreadBeforeRepeat = $unreadViaApi($candidateAToken);
$repeat = api('PATCH', "$baseUrl/company/applications/{$applicationId}/status", [
    'status' => 'under_review',
], $companyToken);
$countAfterRepeat = $countNotificationsForA();
$unreadAfterRepeat = $unreadViaApi($candidateAToken);

pass($report, 'scenario_2_same_status_repeat', (
    $repeat['status'] === 409
    && $countAfterRepeat === $countBeforeRepeat
    && $unreadAfterRepeat === $unreadBeforeRepeat
), [
    'repeat_status' => $repeat['status'],
    'db_count' => $countAfterRepeat,
    'unread_count' => $unreadAfterRepeat,
]);

// --- SCENARIO 3: Different status transition ---
$countBeforeSecond = $countNotificationsForA();
$transition2 = api('PATCH', "$baseUrl/company/applications/{$applicationId}/status", [
    'status' => 'shortlisted',
], $companyToken);
$countAfterSecond = $countNotificationsForA();
$list2 = api('GET', "$baseUrl/candidate/notifications", null, $candidateAToken);
$unreadAfterSecond = $unreadViaApi($candidateAToken);

pass($report, 'scenario_3_different_transition', (
    $transition2['status'] === 200
    && $countAfterSecond === $countBeforeSecond + 1
    && count($list2['body']['data']['items'] ?? []) === 2
    && $unreadAfterSecond === 2
    && $firstNotificationId !== null
    && collect($list2['body']['data']['items'] ?? [])->contains('id', $firstNotificationId)
), [
    'transition_status' => $transition2['status'],
    'db_count' => $countAfterSecond,
    'api_item_count' => count($list2['body']['data']['items'] ?? []),
    'unread_count' => $unreadAfterSecond,
]);

// --- SCENARIO 4: Read state ---
$secondNotificationId = collect($list2['body']['data']['items'] ?? [])
    ->first(fn ($item) => ($item['id'] ?? '') !== $firstNotificationId)['id'] ?? null;

$markOne = api('PATCH', "$baseUrl/candidate/notifications/{$secondNotificationId}/read", null, $candidateAToken);
$unreadAfterOneRead = $unreadViaApi($candidateAToken);
$dbReadAt = Notification::query()->whereKey($secondNotificationId)->value('read_at');
$refetch = api('GET', "$baseUrl/candidate/notifications", null, $candidateAToken);
$refetchedItem = collect($refetch['body']['data']['items'] ?? [])
    ->firstWhere('id', $secondNotificationId);

pass($report, 'scenario_4_read_state', (
    $markOne['status'] === 200
    && ($markOne['body']['data']['is_read'] ?? false) === true
    && $unreadAfterOneRead === 1
    && $dbReadAt !== null
    && ($refetchedItem['is_read'] ?? false) === true
), [
    'mark_read_status' => $markOne['status'],
    'unread_after' => $unreadAfterOneRead,
    'read_at_persisted' => $dbReadAt !== null,
]);

// --- SCENARIO 5: Mark all read ---
$markAll = api('POST', "$baseUrl/candidate/notifications/mark-all-read", null, $candidateAToken);
$unreadAfterAll = $unreadViaApi($candidateAToken);
$remainingUnreadInDb = Notification::query()
    ->where('notifiable_id', $candidateAUserId)
    ->whereNull('read_at')
    ->count();

pass($report, 'scenario_5_mark_all_read', (
    $markAll['status'] === 200
    && ($markAll['body']['data']['updated_count'] ?? 0) >= 1
    && $unreadAfterAll === 0
    && $remainingUnreadInDb === 0
), [
    'updated_count' => $markAll['body']['data']['updated_count'] ?? null,
    'unread_after' => $unreadAfterAll,
    'db_unread_remaining' => $remainingUnreadInDb,
]);

// --- SCENARIO 6: Cross-user isolation ---
$listB = api('GET', "$baseUrl/candidate/notifications", null, $candidateBToken);
$markForeign = api('PATCH', "$baseUrl/candidate/notifications/{$firstNotificationId}/read", null, $candidateBToken);
$guessForeign = api('PATCH', "$baseUrl/candidate/notifications/".($secondNotificationId ?? '00000000-0000-0000-0000-000000000099').'/read', null, $candidateBToken);

pass($report, 'scenario_6_cross_user_isolation', (
    count($listB['body']['data']['items'] ?? []) === 0
    && $markForeign['status'] === 404
    && $guessForeign['status'] === 404
), [
    'candidate_b_list_count' => count($listB['body']['data']['items'] ?? []),
    'mark_foreign_status' => $markForeign['status'],
    'guess_foreign_status' => $guessForeign['status'],
]);

// --- Dedupe lifecycle analysis ---
$report['dedupe_analysis'] = [
    'allowed_transitions' => [
        'submitted' => ['under_review', 'rejected', 'withdrawn'],
        'under_review' => ['shortlisted', 'rejected', 'withdrawn'],
        'shortlisted' => ['interview', 'rejected', 'withdrawn'],
        'interview' => ['offered', 'rejected', 'withdrawn'],
        'offered' => ['rejected', 'withdrawn'],
        'rejected' => [],
        'withdrawn' => [],
    ],
    'status_reversion_possible' => false,
    'dedupe_key_format' => 'application_update:{application_id}:{to_status}',
    'dedupe_safe_for_v1' => true,
    'reason' => 'Each target status is reachable at most once per application; terminal states have no outgoing transitions.',
];

$allPass = collect($report['scenarios'])->every(fn ($s) => ($s['result'] ?? '') === 'PASS');
$report['overall'] = $allPass ? 'PASS' : 'FAIL';

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($allPass ? 0 : 1);
