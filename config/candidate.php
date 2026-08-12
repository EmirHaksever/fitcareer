<?php

declare(strict_types=1);

return [
    'parser_version' => '1.0.0',

    'cv' => [
        'max_size_kb' => (int) env('CANDIDATE_CV_MAX_KB', 5120),
        'allowed_mimes' => ['pdf', 'doc', 'docx'],
        'allowed_extensions' => ['pdf', 'doc', 'docx'],
        'storage_disk' => 'local',
        'storage_path' => 'candidate/cvs',
    ],

    'photo' => [
        'max_size_kb' => (int) env('CANDIDATE_PHOTO_MAX_KB', 2048),
        'allowed_mimes' => ['jpeg', 'jpg', 'png', 'webp'],
        'storage_disk' => 'local',
        'storage_path' => 'candidate/photos',
    ],

    'profile_strength' => [
        'weights' => [
            'headline' => 10,
            'summary' => 15,
            'city' => 5,
            'country' => 5,
            'desired_position' => 10,
            'work_preference' => 5,
            'years_of_experience' => 5,
            'linkedin_url' => 5,
            'experience' => 15,
            'education' => 10,
            'skill' => 10,
            'cv' => 10,
        ],
        'max_score' => 100,
    ],
];
