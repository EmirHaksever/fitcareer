<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\JobSourceType;
use App\Enums\UserRole;
use App\Models\JobSource;
use App\Models\User;
use Tests\TestCase;

class JobSourceHealthTest extends TestCase
{
    public function test_admin_can_view_source_health_summary(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        JobSource::factory()->create([
            'name' => 'Greenhouse Demo',
            'type' => JobSourceType::ApiIntegration,
            'config' => [
                'provider' => 'greenhouse',
                'refresh_interval_minutes' => 360,
            ],
            'last_success_at' => now(),
            'last_run_at' => now(),
            'last_items_found' => 12,
            'last_items_created' => 3,
            'last_items_updated' => 9,
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/job-sources/health')
            ->assertOk();

        $data = $response->json('data');
        $greenhouse = collect($data['items'])->firstWhere('provider', 'greenhouse');

        $this->assertGreaterThanOrEqual(1, $data['summary']['total']);
        $this->assertSame('healthy', $greenhouse['health_status']);
        $this->assertSame(0, $greenhouse['active_published_jobs_count']);
    }

    public function test_non_admin_cannot_view_source_health(): void
    {
        $candidate = User::factory()->create(['role' => UserRole::Candidate]);

        $this->actingAs($candidate)
            ->getJson('/api/v1/admin/job-sources/health')
            ->assertForbidden();
    }
}
