<?php

declare(strict_types=1);

namespace Tests\Feature\Job;

use App\Enums\JobSourceType;
use App\Models\JobSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseCAjaxSeedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Mirrors scripts/seed-lever-sources.php defaults for Ajax Systems.
     *
     * @return array<string, mixed>
     */
    private function leverDefaults(): array
    {
        return [
            'provider' => 'lever',
            'region' => 'global',
            'page_size' => 100,
            'max_pages' => 5,
            'max_listings' => 200,
            'refresh_interval_minutes' => 360,
            'max_posting_age_days' => 365,
            'ingest_policy' => 'turkey_first',
        ];
    }

    private function seedAjaxSystems(): JobSource
    {
        return JobSource::query()->updateOrCreate(
            ['name' => 'Ajax Systems'],
            [
                'base_url' => 'https://api.lever.co/v0/postings/ajax',
                'type' => JobSourceType::ApiIntegration,
                'is_active' => true,
                'config' => array_merge($this->leverDefaults(), [
                    'site_slug' => 'ajax',
                    'company_display_name' => 'Ajax Systems',
                ]),
            ],
        );
    }

    public function test_ajax_systems_source_is_created_with_correct_config(): void
    {
        $source = $this->seedAjaxSystems();

        $this->assertSame('Ajax Systems', $source->name);
        $this->assertSame('https://api.lever.co/v0/postings/ajax', $source->base_url);
        $this->assertTrue($source->is_active);
        $this->assertSame('lever', $source->config['provider']);
        $this->assertSame('ajax', $source->config['site_slug']);
        $this->assertSame('Ajax Systems', $source->config['company_display_name']);
        $this->assertSame('turkey_first', $source->config['ingest_policy']);
        $this->assertSame(365, $source->config['max_posting_age_days']);
    }

    public function test_ajax_systems_seed_is_idempotent(): void
    {
        $first = $this->seedAjaxSystems();
        $second = $this->seedAjaxSystems();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, JobSource::query()->where('name', 'Ajax Systems')->count());
        $this->assertSame('turkey_first', $second->fresh()->config['ingest_policy']);
    }
}
