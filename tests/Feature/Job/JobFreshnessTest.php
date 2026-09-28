<?php

declare(strict_types=1);

namespace Tests\Feature\Job;

use App\Models\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobFreshnessTest extends TestCase
{
    use RefreshDatabase;

    private const CATEGORY = 'freshness-test';

    public function test_newest_sort_orders_by_publish_date_not_scrape_time(): void
    {
        $this->createOldButJustScrapedAndNewJobs();

        $titles = collect($this->getJson('/api/v1/jobs?sort=published_at&category='.self::CATEGORY)
            ->assertOk()
            ->json('data.items'))->pluck('title')->all();

        $this->assertSame(['New job', 'Old job'], $titles);
    }

    public function test_default_sort_is_newest_first(): void
    {
        $this->createOldButJustScrapedAndNewJobs();

        $titles = collect($this->getJson('/api/v1/jobs?category='.self::CATEGORY)
            ->assertOk()
            ->json('data.items'))->pluck('title')->all();

        $this->assertSame(['New job', 'Old job'], $titles);
    }

    public function test_list_and_detail_expose_when_the_job_was_last_seen_at_its_source(): void
    {
        $seenAt = now()->subHours(3)->startOfSecond();
        $job = Job::factory()->published()->scraped()->create([
            'category' => self::CATEGORY,
            'last_seen_at' => $seenAt,
        ]);

        $this->getJson('/api/v1/jobs?category='.self::CATEGORY)
            ->assertOk()
            ->assertJsonPath('data.items.0.last_seen_at', $seenAt->toIso8601String());

        $this->getJson('/api/v1/jobs/'.$job->slug)
            ->assertOk()
            ->assertJsonPath('data.last_seen_at', $seenAt->toIso8601String());
    }

    private function createOldButJustScrapedAndNewJobs(): void
    {
        Job::factory()->published()->scraped()->create([
            'title' => 'Old job',
            'category' => self::CATEGORY,
            'published_at' => now()->subYear(),
            'last_scraped_at' => now(),
        ]);

        Job::factory()->published()->scraped()->create([
            'title' => 'New job',
            'category' => self::CATEGORY,
            'published_at' => now()->subDay(),
            'last_scraped_at' => now()->subHours(6),
        ]);
    }
}
