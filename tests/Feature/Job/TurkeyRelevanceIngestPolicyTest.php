<?php

declare(strict_types=1);

namespace Tests\Feature\Job;

use App\Enums\ImportRunStatus;
use App\Exceptions\ScraperFetchException;
use App\Models\Job;
use App\Services\Scraper\JobIngestionService;
use App\Services\Scraper\JobSourceImportService;
use App\Services\Scraper\JobSourceIngestPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Support\GreenhouseTestSourceFactory;
use Tests\Support\LeverTestSourceFactory;
use Tests\TestCase;

class TurkeyRelevanceIngestPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function leverListingWithLocation(string $location, string $id = 'test-lever-id'): array
    {
        return [
            'id' => $id,
            'text' => 'Software Engineer',
            'descriptionPlain' => 'Build products.',
            'hostedUrl' => 'https://jobs.lever.co/test/'.$id,
            'applyUrl' => 'https://jobs.lever.co/test/'.$id.'/apply',
            'createdAt' => Carbon::parse('2026-06-01')->getTimestampMs(),
            'workplaceType' => str_contains(mb_strtolower($location), 'remote') ? 'remote' : 'onsite',
            'categories' => [
                'location' => $location,
                'allLocations' => [$location],
                'commitment' => 'Full-time',
            ],
        ];
    }

    public function test_turkey_first_source_accepts_istanbul_listing(): void
    {
        $source = LeverTestSourceFactory::create('Test TR Board', 'test-tr', [
            'ingest_policy' => 'turkey_first',
        ]);

        $result = app(JobIngestionService::class)->ingest(
            $source,
            $this->leverListingWithLocation('Istanbul, Turkey'),
        );

        $this->assertTrue($result['created']);
        $this->assertSame('Istanbul', $result['job']->city);
    }

    public function test_turkey_first_source_rejects_global_listing(): void
    {
        $source = LeverTestSourceFactory::create('Test TR Board', 'test-tr', [
            'ingest_policy' => 'turkey_first',
        ]);

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('turkey_first');

        app(JobIngestionService::class)->ingest(
            $source,
            $this->leverListingWithLocation('London, United Kingdom', 'global-id'),
        );
    }

    public function test_global_source_accepts_foreign_listing(): void
    {
        $source = LeverTestSourceFactory::create('Insider One', 'insiderone', [
            'ingest_policy' => 'global',
        ]);

        $result = app(JobIngestionService::class)->ingest(
            $source,
            $this->leverListingWithLocation('Berlin, Germany', 'berlin-id'),
        );

        $this->assertTrue($result['created']);
    }

    public function test_turkey_first_reimport_of_existing_global_job_does_not_update(): void
    {
        $source = LeverTestSourceFactory::create('Legacy Global', 'legacy-global', [
            'ingest_policy' => 'global',
        ]);
        $listing = $this->leverListingWithLocation('London, United Kingdom', 'legacy-global-id');
        $service = app(JobIngestionService::class);

        Carbon::setTestNow('2026-08-01 10:00:00');
        $created = $service->ingest($source, $listing);
        $originalLastSeen = $created['job']->last_seen_at;

        $source->config = array_merge($source->config ?? [], ['ingest_policy' => 'turkey_first']);
        $source->save();

        Carbon::setTestNow('2026-08-12 12:00:00');

        try {
            $service->ingest($source, $listing);
            $this->fail('Expected ingest policy rejection.');
        } catch (ScraperFetchException) {
            // expected
        }

        $job = Job::query()->findOrFail($created['job']->id);
        $this->assertSame(
            $originalLastSeen?->toIso8601String(),
            $job->last_seen_at?->toIso8601String(),
        );

        Carbon::setTestNow();
    }

    public function test_turkey_first_preserves_first_seen_at_on_accepted_reimport(): void
    {
        $source = LeverTestSourceFactory::create('Test TR Board', 'test-tr', [
            'ingest_policy' => 'turkey_first',
        ]);
        $listing = $this->leverListingWithLocation('Ankara, Turkey', 'ankara-id');
        $service = app(JobIngestionService::class);

        Carbon::setTestNow('2026-08-01 10:00:00');
        $created = $service->ingest($source, $listing);
        $originalFirstSeen = $created['job']->first_seen_at;

        Carbon::setTestNow('2026-08-12 12:00:00');
        $listing['descriptionPlain'] = 'Updated description.';
        $updated = $service->ingest($source, $listing);

        $this->assertFalse($updated['created']);
        $this->assertSame(
            $originalFirstSeen?->toIso8601String(),
            $updated['job']->first_seen_at?->toIso8601String(),
        );

        Carbon::setTestNow();
    }

    public function test_global_source_import_regression_smoke(): void
    {
        $source = GreenhouseTestSourceFactory::create('Good Job Games', 'goodjobgames', [
            'ingest_policy' => 'global',
        ]);

        Http::fake([
            'https://boards-api.greenhouse.io/v1/boards/goodjobgames/jobs*' => Http::response(
                GreenhouseTestSourceFactory::loadFixture('goodjobgames-single.json'),
                200,
            ),
        ]);

        $result = app(JobSourceImportService::class)->import($source);

        $this->assertSame(ImportRunStatus::Completed, $result['run']->status);
        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['failed']);
    }

    public function test_turkey_first_greenhouse_import_rejects_non_tr_fixture(): void
    {
        $source = GreenhouseTestSourceFactory::create('Global GH', 'globalgh', [
            'ingest_policy' => 'turkey_first',
        ]);

        Http::fake([
            'https://boards-api.greenhouse.io/v1/boards/globalgh/jobs*' => Http::response([
                'jobs' => [[
                    'id' => 999,
                    'title' => 'Global Role',
                    'updated_at' => '2026-06-11T05:20:10-04:00',
                    'location' => ['name' => 'San Francisco, CA'],
                    'content' => '<p>Global job.</p>',
                ]],
            ], 200),
        ]);

        $result = app(JobSourceImportService::class)->import($source);

        $this->assertSame(1, $result['failed']);
        $this->assertSame(0, $result['created']);
        $this->assertSame(0, Job::query()->where('job_source_id', $source->id)->count());
    }

    public function test_duplicate_idempotency_after_policy_rejection(): void
    {
        $source = LeverTestSourceFactory::create('Test TR Board', 'test-tr', [
            'ingest_policy' => 'turkey_first',
        ]);
        $listing = $this->leverListingWithLocation('Istanbul, Turkey', 'dup-id');
        $service = app(JobIngestionService::class);

        $first = $service->ingest($source, $listing);
        $second = $service->ingest($source, $listing);

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame(1, Job::query()->where('job_source_id', $source->id)->count());
    }

    public function test_remote_open_accepts_ambiguous_remote(): void
    {
        $source = LeverTestSourceFactory::create('Remotive', 'remotive', [
            'ingest_policy' => 'remote_open',
        ]);

        $result = app(JobIngestionService::class)->ingest(
            $source,
            $this->leverListingWithLocation('Remote', 'remote-ambiguous'),
        );

        $this->assertTrue($result['created']);
    }

    public function test_remote_open_rejects_worldwide_remote(): void
    {
        $source = LeverTestSourceFactory::create('Remotive', 'remotive', [
            'ingest_policy' => 'remote_open',
        ]);

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('remote_open');

        app(JobIngestionService::class)->ingest(
            $source,
            $this->leverListingWithLocation('Remote Worldwide', 'remote-world'),
        );
    }

    public function test_default_policy_without_config_is_global(): void
    {
        $source = LeverTestSourceFactory::create('Legacy Board', 'legacy');

        $policy = app(JobSourceIngestPolicyService::class)->resolvePolicy($source);

        $this->assertSame('global', $policy->value);
    }

    public function test_provider_updated_at_preserved_on_accepted_reimport(): void
    {
        $source = GreenhouseTestSourceFactory::create('Good Job Games', 'goodjobgames', [
            'ingest_policy' => 'turkey_first',
        ]);
        $rawListing = GreenhouseTestSourceFactory::loadFixture('goodjobgames-single.json')['jobs'][0];
        $service = app(JobIngestionService::class);

        $created = $service->ingest($source, $rawListing);
        $this->assertSame('2026-06-11', $created['job']->provider_updated_at?->toDateString());

        $updated = $service->ingest($source, $rawListing);
        $this->assertSame('2026-06-11', $updated['job']->provider_updated_at?->toDateString());
    }
}
