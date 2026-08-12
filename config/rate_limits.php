<?php

declare(strict_types=1);

return [
    'job_refresh' => [
        'per_user_per_minute' => (int) env('RATE_LIMIT_JOB_REFRESH_PER_MINUTE', 5),
        'per_user_per_job_cooldown_seconds' => (int) env('RATE_LIMIT_JOB_REFRESH_COOLDOWN', 300),
    ],
    'job_search' => [
        'per_user_per_minute' => (int) env('RATE_LIMIT_JOB_SEARCH_PER_MINUTE', 60),
    ],
    'ai_trigger' => [
        'per_user_per_minute' => (int) env('RATE_LIMIT_AI_TRIGGER_PER_MINUTE', 10),
    ],
    'job_report' => [
        'per_user_per_minute' => (int) env('RATE_LIMIT_JOB_REPORT_PER_MINUTE', 5),
    ],
    'auth' => [
        'login_per_minute_by_ip' => (int) env('RATE_LIMIT_LOGIN_IP_PER_MINUTE', 10),
        'login_per_minute_by_email' => (int) env('RATE_LIMIT_LOGIN_EMAIL_PER_MINUTE', 5),
        'register_per_minute' => (int) env('RATE_LIMIT_REGISTER_PER_MINUTE', 3),
        'password_reset_per_minute_by_ip' => (int) env('RATE_LIMIT_PW_RESET_IP_PER_MINUTE', 10),
        'password_reset_per_minute_by_email' => (int) env('RATE_LIMIT_PW_RESET_EMAIL_PER_MINUTE', 5),
    ],
];
