<?php

declare(strict_types=1);

namespace Tests\Feature\Job;

use App\Enums\ExperienceLevel;
use App\Models\Job;
use App\Services\Scraper\JobIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\AshbyTestSourceFactory;
use Tests\Support\GreenhouseTestSourceFactory;
use Tests\Support\LeverTestSourceFactory;
use Tests\Support\RecruiteeTestSourceFactory;
use Tests\TestCase;

class JobIngestionTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_job_gets_first_seen_at_timestamp(): void
    {
        $source = LeverTestSourceFactory::create('Commencis', 'commencis');
        $rawListing = LeverTestSourceFactory::loadFixture('commencis-single.json')[0];

        Carbon::setTestNow('2026-08-12 12:00:00');

        $result = app(JobIngestionService::class)->ingest($source, $rawListing);

        $this->assertTrue($result['created']);
        $this->assertNotNull($result['job']->first_seen_at);
        $this->assertSame('2026-08-12 12:00:00', $result['job']->first_seen_at?->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_existing_job_preserves_first_seen_at_on_reimport(): void
    {
        $source = LeverTestSourceFactory::create('Commencis', 'commencis');
        $rawListing = LeverTestSourceFactory::loadFixture('commencis-single.json')[0];
        $service = app(JobIngestionService::class);

        Carbon::setTestNow('2026-08-01 10:00:00');
        $created = $service->ingest($source, $rawListing);
        $originalFirstSeen = $created['job']->first_seen_at;

        Carbon::setTestNow('2026-08-12 12:00:00');
        $rawListing['descriptionPlain'] = 'Updated description for reimport.';
        $updated = $service->ingest($source, $rawListing);

        $this->assertFalse($updated['created']);
        $this->assertSame(
            $originalFirstSeen?->toIso8601String(),
            $updated['job']->first_seen_at?->toIso8601String(),
        );

        Carbon::setTestNow();
    }

    public function test_greenhouse_maps_provider_updated_at(): void
    {
        $source = GreenhouseTestSourceFactory::create('Good Job Games', 'goodjobgames');
        $rawListing = GreenhouseTestSourceFactory::loadFixture('goodjobgames-single.json')['jobs'][0];

        $result = app(JobIngestionService::class)->ingest($source, $rawListing);

        $this->assertNotNull($result['job']->provider_updated_at);
        $this->assertSame('2026-06-11', $result['job']->provider_updated_at?->toDateString());
    }

    public function test_ashby_provider_updated_at_remains_null(): void
    {
        $source = AshbyTestSourceFactory::create('Ashby Co', 'ashby-co');
        $rawListing = AshbyTestSourceFactory::loadFixture('codeway-single.json')['jobs'][0];

        $result = app(JobIngestionService::class)->ingest($source, $rawListing);

        $this->assertNull($result['job']->provider_updated_at);
    }

    public function test_recruitee_maps_provider_updated_at(): void
    {
        $source = RecruiteeTestSourceFactory::create('Mikro Yazılım', 'mikroyazilim');
        $rawListing = RecruiteeTestSourceFactory::turkeyListing();

        $result = app(JobIngestionService::class)->ingest($source, $rawListing);

        $this->assertNotNull($result['job']->provider_updated_at);
        $this->assertSame('2026-08-12', $result['job']->provider_updated_at?->toDateString());
    }

    public function test_null_provider_updated_at_does_not_erase_existing_timestamp(): void
    {
        $source = LeverTestSourceFactory::create('Commencis', 'commencis');
        $rawListing = LeverTestSourceFactory::loadFixture('commencis-single.json')[0];
        $service = app(JobIngestionService::class);

        $created = $service->ingest($source, $rawListing);
        $job = $created['job'];
        $job->provider_updated_at = Carbon::parse('2026-07-01 10:00:00');
        $job->save();

        $rawListing['descriptionPlain'] = 'Another update without provider timestamp.';
        $updated = $service->ingest($source, $rawListing);

        $this->assertSame('2026-07-01', $updated['job']->provider_updated_at?->toDateString());
    }

    public function test_senior_title_is_inferred_during_ingest(): void
    {
        $source = LeverTestSourceFactory::create('Commencis', 'commencis');
        $rawListing = LeverTestSourceFactory::loadFixture('commencis-single.json')[0];

        $result = app(JobIngestionService::class)->ingest($source, $rawListing);

        $this->assertSame(ExperienceLevel::Senior, $result['job']->experience_level);
    }

    public function test_generic_title_remains_unknown_during_ingest(): void
    {
        $source = LeverTestSourceFactory::create('Commencis', 'commencis-generic');
        $rawListing = LeverTestSourceFactory::loadFixture('commencis-single.json')[0];
        $rawListing['id'] = 'generic-software-engineer-id';
        $rawListing['text'] = 'Software Engineer';

        $result = app(JobIngestionService::class)->ingest($source, $rawListing);

        $this->assertNull($result['job']->experience_level);
    }
}
