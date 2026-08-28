<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Replicate frontend isLikelyEnglishText logic in PHP for sample verification
function isLikelyEnglishText(string $text): bool
{
    if (! preg_match('/[ğüşıöçĞÜŞİÖÇ]/u', $text) === 1 && preg_match('/[ğüşıöçĞÜŞİÖÇ]/u', $text)) {
        return false;
    }
    if (preg_match('/[ğüşıöçĞÜŞİÖÇ]/u', $text)) {
        return false;
    }
    $normalized = strtolower(preg_replace('/[^a-z0-9\s]/i', ' ', $text));
    $words = array_values(array_filter(preg_split('/\s+/', $normalized)));
    if (count($words) < 12) {
        return false;
    }
    $hints = ['the', 'and', 'you', 'your', 'will', 'with', 'our', 'we', 'are', 'for', 'this', 'that', 'have', 'from', 'about', 'role', 'team', 'work', 'experience', 'requirements', 'responsibilities'];
    $hits = count(array_filter($words, fn ($w) => in_array($w, $hints, true)));

    return $hits >= 3 && ($hits / count($words)) >= 0.08;
}

$baseUrl = getenv('SMOKE_API_BASE') ?: 'http://127.0.0.1:8000/api/v1';

function fetchJob(string $slug): ?array
{
    global $baseUrl;
    $raw = file_get_contents("$baseUrl/jobs/$slug");
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);

    return $data['data'] ?? null;
}

$samples = [
    'turkish' => 'saglik-sigortasi-satis-uzmani-43E9278F7F',
    'english' => 'software-engineer-3DC7AB606B',
];

$out = [];
foreach ($samples as $label => $slug) {
    $job = fetchJob($slug);
    if ($job === null) {
        $out[$label] = ['error' => 'not found'];
        continue;
    }
    $desc = $job['description'] ?? '';
    $out[$label] = [
        'title' => $job['title'],
        'slug' => $slug,
        'is_likely_english' => isLikelyEnglishText($desc),
        'desc_length' => strlen($desc),
    ];
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
