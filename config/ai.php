<?php

declare(strict_types=1);

return [
    'provider' => env('AI_PROVIDER', 'gemini'),

    'cv_extraction' => [
        'enabled' => env('AI_CV_EXTRACTION_ENABLED', true),
        'prompt_version' => 'cv-extract-v1',
        'max_cv_chars' => (int) env('AI_CV_EXTRACTION_MAX_CHARS', 12000),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-flash-latest'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 30),
    ],
];
