<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiHealthTest extends TestCase
{
    public function test_versioned_api_health_endpoint_uses_standard_response_shape(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'FitCareer API is available.',
                'data' => null,
                'errors' => null,
            ]);
    }
}
