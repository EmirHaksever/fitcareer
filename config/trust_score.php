<?php

declare(strict_types=1);

return [
    'version' => '1.0.0',

    'weights' => [
        'company_verification' => 25,
        'source_reliability' => 15,
        'contact_information' => 15,
        'content_completeness' => 10,
        'job_freshness' => 10,
        'report_penalty' => 10,
        'salary_transparency' => 5,
        'moderation' => 10,
    ],

    'labels' => [
        'verified' => 75,
        'unrated' => 50,
        'suspicious' => 30,
    ],

    'thresholds' => [
        'content' => [
            'min_description_length' => 100,
            'min_title_length' => 5,
        ],
        'freshness' => [
            'fresh_days' => 30,
            'stale_days' => 90,
        ],
        'reports' => [
            'open_statuses' => ['reported', 'reviewing'],
            'serious_reasons' => [
                'scam_suspected',
                'suspicious_job',
                'misleading_salary',
                'personal_information_request',
            ],
            'penalty_per_open_report' => 15,
            'penalty_per_serious_report' => 10,
        ],
        'source' => [
            'internal' => 90,
            'api_integration' => 70,
            'scraper' => 55,
            'scraped_without_source' => 45,
        ],
        'company_verification' => [
            'verified' => 95,
            'verified_without_flag' => 85,
            'pending' => 55,
            'unverified' => 45,
            'rejected' => 25,
        ],
    ],
];
