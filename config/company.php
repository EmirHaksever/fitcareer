<?php

declare(strict_types=1);

return [
    'logo' => [
        'max_size_kb' => (int) env('COMPANY_LOGO_MAX_KB', 2048),
        'allowed_mimes' => ['jpeg', 'jpg', 'png', 'webp'],
        'storage_disk' => 'local',
        'storage_path' => 'company/logos',
    ],
];
