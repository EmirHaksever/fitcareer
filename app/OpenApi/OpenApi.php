<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'FitCareer API',
    description: 'FitCareer REST API documentation.',
)]
#[OA\Server(
    url: '/api/v1',
    description: 'API version 1',
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum',
)]
class OpenApi {}
