<?php

declare(strict_types=1);

namespace Tests\Feature\Job;

use App\Models\Job;
use App\Models\JobSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_count_the_whole_catalog_not_a_page(): void
    {
        // Some suites leave committed rows behind, so assert on the delta.
        $before = $this->getJson('/api/v1/stats')->assertOk()->json('data');
        Cache::flush();

        Job::factory()->count(2)->published()->withTrustScore()->create();
        Job::factory()->published()->create();
        Job::factory()->draft()->create();
        Job::factory()->published()->create(['expires_at' => now()->subDay()]);
        Job::factory()->published()->scraped()->create(['city' => 'Berlin', 'country' => 'Germany']);

        JobSource::factory()->count(2)->create(['is_active' => true]);
        JobSource::factory()->create(['is_active' => false]);

        $this->getJson('/api/v1/stats')
            ->assertOk()
            ->assertJsonPath('data.published_jobs', $before['published_jobs'] + 3)
            ->assertJsonPath('data.trust_analyzed_jobs', $before['trust_analyzed_jobs'] + 2)
            ->assertJsonPath('data.active_sources', $before['active_sources'] + 2);
    }

    public function test_stats_are_cached_between_requests(): void
    {
        $first = $this->getJson('/api/v1/stats')->json('data.published_jobs');

        Job::factory()->published()->create();

        $this->getJson('/api/v1/stats')->assertJsonPath('data.published_jobs', $first);
    }

    public function test_published_count_matches_the_job_list_total(): void
    {
        Job::factory()->count(3)->published()->create();
        Job::factory()->published()->create(['expires_at' => now()->subDay()]);

        $listTotal = $this->getJson('/api/v1/jobs')->json('data.pagination.total');

        $this->getJson('/api/v1/stats')
            ->assertOk()
            ->assertJsonPath('data.published_jobs', $listTotal);
    }
}
