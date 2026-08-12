<?php

declare(strict_types=1);

$baseUrl = $argv[1] ?? 'http://127.0.0.1:8000/api/v1';
$timestamp = date('YmdHis');
$candidateEmail = "dev.candidate.{$timestamp}@fitcareer.test";
$companyEmail = "dev.company.{$timestamp}@fitcareer.test";
$password = 'Password123!';

function request(string $method, string $url, ?array $payload = null, ?string $token = null): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json', 'Content-Type: application/json'];

    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADER => true,
    ]);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
    }

    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $body = substr((string) $raw, $headerSize);
    $json = json_decode($body, true);

    return [
        'status' => $status,
        'body' => $json,
        'raw' => $body,
    ];
}

function assertStatus(array $response, int $expected, string $label): void
{
    $actual = $response['status'];
    $ok = $actual === $expected;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . " (expected {$expected}, got {$actual})" . PHP_EOL;

    if (!$ok) {
        echo '  Response: ' . $response['raw'] . PHP_EOL;
    }
}

function assertTrue(bool $condition, string $label): void
{
    echo ($condition ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
}

echo "API_BASE={$baseUrl}" . PHP_EOL;
echo "CANDIDATE_EMAIL={$candidateEmail}" . PHP_EOL;
echo "COMPANY_EMAIL={$companyEmail}" . PHP_EOL;
echo "PASSWORD={$password}" . PHP_EOL . PHP_EOL;

// TEST: empty login validation
$emptyLogin = request('POST', "{$baseUrl}/auth/login", ['email' => '', 'password' => '']);
assertStatus($emptyLogin, 422, 'TEST empty login returns 422');
assertTrue(isset($emptyLogin['body']['errors']['email']), 'TEST empty email validation error present');
assertTrue(isset($emptyLogin['body']['errors']['password']), 'TEST empty password validation error present');

// TEST: invalid email format
$invalidEmail = request('POST', "{$baseUrl}/auth/login", ['email' => 'not-an-email', 'password' => 'x']);
assertStatus($invalidEmail, 422, 'TEST invalid email format returns 422');
assertTrue(isset($invalidEmail['body']['errors']['email']), 'TEST invalid email validation error present');

// TEST: wrong password
$wrongPassword = request('POST', "{$baseUrl}/auth/login", ['email' => 'missing@example.com', 'password' => 'wrongpass']);
assertStatus($wrongPassword, 401, 'TEST wrong credentials returns 401');
assertTrue(($wrongPassword['body']['message'] ?? '') === 'Invalid credentials.', 'TEST wrong credentials message');

// TEST: register candidate
$candidateRegister = request('POST', "{$baseUrl}/auth/register", [
    'name' => 'Dev Candidate',
    'email' => $candidateEmail,
    'password' => $password,
    'password_confirmation' => $password,
    'role' => 'candidate',
]);
assertStatus($candidateRegister, 201, 'TEST candidate register returns 201');
$candidateToken = $candidateRegister['body']['data']['token'] ?? null;
assertTrue(is_string($candidateToken) && $candidateToken !== '', 'TEST candidate token issued');
assertTrue(($candidateRegister['body']['data']['user']['role'] ?? '') === 'candidate', 'TEST candidate role');

// TEST: login candidate
$candidateLogin = request('POST', "{$baseUrl}/auth/login", [
    'email' => $candidateEmail,
    'password' => $password,
]);
assertStatus($candidateLogin, 200, 'TEST candidate login success');
$loginToken = $candidateLogin['body']['data']['token'] ?? null;
assertTrue(is_string($loginToken) && $loginToken !== '', 'TEST login token issued');

// TEST: me endpoint
$me = request('GET', "{$baseUrl}/auth/me", null, $loginToken);
assertStatus($me, 200, 'TEST /auth/me with token');
assertTrue(($me['body']['data']['email'] ?? '') === $candidateEmail, 'TEST /auth/me returns correct email');

// TEST: protected route without token
$unauthMe = request('GET', "{$baseUrl}/auth/me");
assertStatus($unauthMe, 401, 'TEST /auth/me without token returns 401');

// TEST: candidate protected route
$candidateDashboard = request('GET', "{$baseUrl}/candidate/dashboard", null, $loginToken);
assertStatus($candidateDashboard, 200, 'TEST candidate protected route with candidate token');

// TEST: logout
$logout = request('POST', "{$baseUrl}/auth/logout", null, $loginToken);
assertStatus($logout, 200, 'TEST logout success');
$meAfterLogout = request('GET', "{$baseUrl}/auth/me", null, $loginToken);
assertStatus($meAfterLogout, 401, 'TEST token revoked after logout');

// TEST: register company
$companyRegister = request('POST', "{$baseUrl}/auth/register", [
    'name' => 'Dev Company Ltd',
    'email' => $companyEmail,
    'password' => $password,
    'password_confirmation' => $password,
    'role' => 'company',
    'company_name' => 'Dev Company Ltd',
]);
assertStatus($companyRegister, 201, 'TEST company register returns 201');
$companyToken = $companyRegister['body']['data']['token'] ?? null;
assertTrue(($companyRegister['body']['data']['user']['role'] ?? '') === 'company', 'TEST company role');

// TEST: company protected route
$companyDashboard = request('GET', "{$baseUrl}/company/dashboard", null, $companyToken);
assertStatus($companyDashboard, 200, 'TEST company protected route with company token');

// TEST: role mismatch (candidate token on company route)
$roleMismatch = request('GET', "{$baseUrl}/company/dashboard", null, $loginToken);
assertStatus($roleMismatch, 403, 'TEST candidate token on company route returns 403');

// TEST: register validation mismatch password
$passwordMismatch = request('POST', "{$baseUrl}/auth/register", [
    'name' => 'Bad User',
    'email' => "bad.{$timestamp}@fitcareer.test",
    'password' => $password,
    'password_confirmation' => 'Different123!',
    'role' => 'candidate',
]);
assertStatus($passwordMismatch, 422, 'TEST password confirmation mismatch returns 422');

// TEST: company without company_name
$missingCompanyName = request('POST', "{$baseUrl}/auth/register", [
    'name' => 'No Company Name',
    'email' => "nocompany.{$timestamp}@fitcareer.test",
    'password' => $password,
    'password_confirmation' => $password,
    'role' => 'company',
]);
assertStatus($missingCompanyName, 422, 'TEST company register without company_name returns 422');
assertTrue(isset($missingCompanyName['body']['errors']['company_name']), 'TEST company_name validation error present');

echo PHP_EOL . 'DONE' . PHP_EOL;
