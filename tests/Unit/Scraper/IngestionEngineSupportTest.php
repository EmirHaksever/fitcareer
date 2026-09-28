<?php

declare(strict_types=1);

namespace Tests\Unit\Scraper;

use App\Enums\JobStatus;
use App\Jobs\RunJobSourceImportJob;
use App\Models\Job;
use App\Models\JobSource;
use App\Repositories\Eloquent\MySqlFulltextJobSearchRepository;
use App\Services\Job\JobService;
use App\DTOs\JobSearchQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IngestionEngineSupportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function search_excludes_published_jobs_with_past_expires_at(): void
    {
        Job::factory()->published()->create([
            'expires_at' => now()->subDay(),
            'title' => 'Expired Listing',
        ]);

        Job::factory()->published()->create([
            'expires_at' => now()->addDay(),
            'title' => 'Active Listing',
        ]);

        $results = app(MySqlFulltextJobSearchRepository::class)->search(new JobSearchQuery(
            page: 1,
            perPage: 20,
        ));

        $this->assertSame(1, $results->total());
        $this->assertSame('Active Listing', $results->items()[0]->title);
    }

    #[Test]
    public function detail_endpoint_hides_expired_published_jobs(): void
    {
        $job = Job::factory()->create([
            'status' => JobStatus::Published,
            'expires_at' => now()->subHour(),
            'slug' => 'expired-job-slug',
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(JobService::class)->getPublishedBySlug($job->slug);
    }

    #[Test]
    public function scheduler_command_dispatches_due_sources(): void
    {
        Queue::fake();

        JobSource::factory()->create([
            'name' => 'Due Source',
            'is_active' => true,
            'last_run_at' => now()->subHours(10),
            'config' => ['provider' => 'remotive', 'refresh_interval_minutes' => 60],
        ]);

        JobSource::factory()->create([
            'name' => 'Not Due Source',
            'is_active' => true,
            'last_run_at' => now()->subMinutes(5),
            'config' => ['provider' => 'remotive', 'refresh_interval_minutes' => 60],
        ]);

        Artisan::call('jobs:dispatch-scheduled-imports');

        Queue::assertPushed(RunJobSourceImportJob::class, 1);
    }
}
