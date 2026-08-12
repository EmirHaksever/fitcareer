<?php

declare(strict_types=1);

$baseUrl = 'http://127.0.0.1:8000/api/v1';
$email = 'dev.candidate.20260811000532@fitcareer.test';
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
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
    }
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => json_decode((string) $body, true), 'raw' => (string) $body];
}

$login = request('POST', "$baseUrl/auth/login", ['email' => $email, 'password' => $password]);
echo 'LOGIN_STATUS=' . $login['status'] . PHP_EOL;
$token = $login['body']['data']['token'] ?? '';
echo 'TOKEN_LEN=' . strlen($token) . PHP_EOL;

$candidateProfile = request('GET', "$baseUrl/candidate/profile", null, $token);
echo 'CANDIDATE_PROFILE_STATUS=' . $candidateProfile['status'] . PHP_EOL;

$companyLogin = request('POST', "$baseUrl/auth/login", [
    'email' => 'dev.company.20260811000532@fitcareer.test',
    'password' => $password,
]);
$companyToken = $companyLogin['body']['data']['token'] ?? '';
$companyProfile = request('GET', "$baseUrl/company/profile", null, $companyToken);
echo 'COMPANY_PROFILE_STATUS=' . $companyProfile['status'] . PHP_EOL;

$roleMismatch = request('GET', "$baseUrl/company/profile", null, $token);
echo 'ROLE_MISMATCH_STATUS=' . $roleMismatch['status'] . PHP_EOL;

$logout = request('POST', "$baseUrl/auth/logout", null, $token);
echo 'LOGOUT_STATUS=' . $logout['status'] . PHP_EOL;
$meAfter = request('GET', "$baseUrl/auth/me", null, $token);
echo 'ME_AFTER_LOGOUT_STATUS=' . $meAfter['status'] . PHP_EOL;
