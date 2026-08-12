<?php

declare(strict_types=1);

return [
    'version' => 'fit-v1',

    'weights' => [
        'required_skills' => 35,
        'preferred_skills' => 15,
        'experience' => 20,
        'work_type' => 15,
        'location' => 10,
        'salary' => 5,
    ],

    'fallback_confidence' => 1.0,

    'score' => [
        'min' => 0,
        'max' => 100,
    ],

    'thresholds' => [
        'experience' => [
            'levels' => [
                'intern' => ['min_years' => 0, 'ideal_years' => 0],
                'entry' => ['min_years' => 0, 'ideal_years' => 1],
                'mid' => ['min_years' => 2, 'ideal_years' => 4],
                'senior' => ['min_years' => 5, 'ideal_years' => 8],
                'lead' => ['min_years' => 8, 'ideal_years' => 10],
                'executive' => ['min_years' => 12, 'ideal_years' => 15],
            ],
            'meets_or_exceeds' => 100,
            'slightly_below' => 60,
            'well_below' => 20,
            'slightly_below_gap_years' => 2,
        ],
        'work_type' => [
            'exact_match' => 100,
            'any_preference' => 100,
            'partial_match' => 50,
            'mismatch' => 0,
            'compatibility' => [
                'remote' => ['remote' => 100, 'hybrid' => 50, 'onsite' => 0],
                'hybrid' => ['remote' => 50, 'hybrid' => 100, 'onsite' => 50],
                'onsite' => ['remote' => 0, 'hybrid' => 50, 'onsite' => 100],
            ],
        ],
        'location' => [
            'remote_bypass_score' => 100,
            'same_city' => 100,
            'same_country' => 50,
            'different_country' => 0,
        ],
        'salary' => [
            'full_overlap' => 100,
            'no_overlap' => 0,
        ],
    ],
];
