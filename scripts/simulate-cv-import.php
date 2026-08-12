<?php

declare(strict_types=1);

/**
 * Simulates frontend CV import against the API and prints every failing payload.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CandidateProfile;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

$email = $argv[1] ?? 'dev.candidate.20260811000532@fitcareer.test';
$user = User::where('email', $email)->first();

if (! $user) {
    fwrite(STDERR, "User not found\n");
    exit(1);
}

$profile = CandidateProfile::where('user_id', $user->id)->first();
$parsed = $profile?->cv_parsed_data;

if (! is_array($parsed)) {
    fwrite(STDERR, "No parsed CV on profile\n");
    exit(1);
}

$samplePath = base_path('scripts/cv-sample.json');
if (file_exists($samplePath)) {
    $parsed = json_decode(file_get_contents($samplePath), true, 512, JSON_THROW_ON_ERROR);
}

$token = $user->createToken('cv-import-test')->plainTextToken;
$base = rtrim((string) env('APP_URL', 'http://127.0.0.1:8000'), '/');
if (str_contains($base, '/fitcareer/public')) {
    $apiBase = $base.'/api/v1';
} else {
    $apiBase = $base.'/api/v1';
}

function apiRequest(string $method, string $url, string $token, ?array $payload = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => array_filter([
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer '.$token,
        ]),
        CURLOPT_POSTFIELDS => $payload !== null ? json_encode($payload) : null,
    ]);

    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $status,
        'body' => json_decode((string) $body, true),
        'raw' => $body,
    ];
}

function fail(string $label, array $response): void
{
    echo "FAIL [{$label}] HTTP {$response['status']}\n";
    echo json_encode($response['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n\n";
}

// Seed skills if needed
foreach ([
    'JavaScript', 'TypeScript', 'React', 'Vue.js', 'PHP', 'Laravel',
    'Python', 'Java', 'SQL', 'Git', 'Docker', 'AWS', 'Node.js',
    'HTML', 'CSS', 'Product Management', 'Agile', 'Scrum',
] as $name) {
    Skill::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'category' => 'Technology']);
}

// Load frontend-built plan via node
$node = 'node';
$script = <<<'JS'
import { readFileSync } from 'node:fs';
import { extractCvImportPlan } from '../frontend/src/utils/cvImport.ts';

const parsed = JSON.parse(readFileSync('./scripts/cv-sample.json', 'utf8'));
console.log(JSON.stringify(extractCvImportPlan(parsed)));
JS;

file_put_contents(base_path('scripts/_cv-plan.mjs'), $script);
$planJson = shell_exec('cd '.escapeshellarg(base_path()).' && node scripts/_cv-plan.mjs 2>&1');

if (! is_string($planJson) || ! str_starts_with(trim($planJson), '{')) {
    fwrite(STDERR, "Failed to build plan via node:\n{$planJson}\n");
    exit(1);
}

$plan = json_decode($planJson, true, 512, JSON_THROW_ON_ERROR);
$failures = 0;

echo "Plan summary:\n";
echo '- profile keys: '.implode(', ', array_keys($plan['profile'] ?? []))."\n";
echo '- experiences: '.count($plan['experiences'] ?? [])."\n";
echo '- educations: '.count($plan['educations'] ?? [])."\n";
echo '- projects: '.count($plan['projects'] ?? [])."\n";
echo '- skills: '.count($plan['skillNames'] ?? [])."\n\n";

if (! empty($plan['profile'])) {
    $response = apiRequest('PUT', $apiBase.'/candidate/profile', $token, $plan['profile']);
    if ($response['status'] !== 200) {
        fail('profile', $response);
        $failures++;
    } else {
        echo "OK profile\n";
    }
}

foreach ($plan['experiences'] ?? [] as $index => $payload) {
    $response = apiRequest('POST', $apiBase.'/candidate/experiences', $token, $payload);
    if (! in_array($response['status'], [201, 200], true)) {
        fail("experience #{$index}", $response);
        $failures++;
    } else {
        echo "OK experience #{$index}\n";
    }
}

foreach ($plan['educations'] ?? [] as $index => $payload) {
    $response = apiRequest('POST', $apiBase.'/candidate/educations', $token, $payload);
    if (! in_array($response['status'], [201, 200], true)) {
        fail("education #{$index}", $response);
        $failures++;
    } else {
        echo "OK education #{$index}\n";
    }
}

foreach ($plan['projects'] ?? [] as $index => $payload) {
    $response = apiRequest('POST', $apiBase.'/candidate/projects', $token, $payload);
    if (! in_array($response['status'], [201, 200], true)) {
        fail("project #{$index}", $response);
        $failures++;
    } else {
        echo "OK project #{$index}\n";
    }
}

$catalog = Skill::query()->get();
foreach ($plan['skillNames'] ?? [] as $skillName) {
    $normalized = mb_strtolower(str_replace('.', '', trim($skillName)));
    $matched = $catalog->first(fn ($s) => mb_strtolower(str_replace('.', '', $s->name)) === $normalized);
    if (! $matched) {
        echo "SKIP skill {$skillName}\n";
        continue;
    }

    $response = apiRequest('POST', $apiBase.'/candidate/skills', $token, ['skill_id' => $matched->id]);
    if (! in_array($response['status'], [201, 200], true)) {
        fail("skill {$skillName}", $response);
        $failures++;
    } else {
        echo "OK skill {$skillName}\n";
    }
}

@unlink(base_path('scripts/_cv-plan.mjs'));

echo $failures === 0 ? "\nALL OK\n" : "\n{$failures} FAILURES\n";
exit($failures === 0 ? 0 : 1);
