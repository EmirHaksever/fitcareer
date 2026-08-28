<?php

declare(strict_types=1);

namespace Tests\Feature\Job;

use App\Enums\JobSourceType;
use App\Models\JobSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseASeedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array{name: string, site_slug: string, company_display_name: string, provider: string, base_url: string}  $board
     */
    private function seedBoard(array $board, array $defaults): JobSource
    {
        return JobSource::query()->updateOrCreate(
            ['name' => $board['name']],
            [
                'base_url' => $board['base_url'],
                'type' => JobSourceType::ApiIntegration,
                'is_active' => true,
                'config' => array_merge($defaults, [
                    'site_slug' => $board['site_slug'],
                    'company_display_name' => $board['company_display_name'],
                ]),
            ],
        );
    }

    public function test_phase_a_lever_seed_is_idempotent(): void
    {
        $defaults = [
            'provider' => 'lever',
            'page_size' => 100,
            'max_pages' => 5,
            'max_listings' => 200,
            'refresh_interval_minutes' => 360,
            'max_posting_age_days' => 365,
        ];

        $board = [
            'name' => 'iyzico',
            'site_slug' => 'iyzico',
            'company_display_name' => 'iyzico',
            'base_url' => 'https://api.lever.co/v0/postings/iyzico',
        ];

        $first = $this->seedBoard($board, $defaults);
        $second = $this->seedBoard($board, $defaults);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, JobSource::query()->where('name', 'iyzico')->count());
        $this->assertSame('lever', $second->config['provider']);
        $this->assertSame('iyzico', $second->config['site_slug']);
    }

    public function test_phase_a_greenhouse_and_workable_seed_configs(): void
    {
        $greenhouse = $this->seedBoard([
            'name' => 'Zynga',
            'site_slug' => 'zyngacareers',
            'company_display_name' => 'Zynga',
            'base_url' => 'https://boards-api.greenhouse.io/v1/boards/zyngacareers/jobs',
        ], [
            'provider' => 'greenhouse',
            'max_listings' => 200,
            'max_posting_age_days' => 365,
        ]);

        $workable = $this->seedBoard([
            'name' => 'FERASET',
            'site_slug' => 'feraset',
            'company_display_name' => 'FERASET',
            'base_url' => 'https://apply.workable.com/api/v1/widget/accounts/feraset',
        ], [
            'provider' => 'workable',
            'max_listings' => 200,
            'max_posting_age_days' => 365,
        ]);

        $this->assertSame('greenhouse', $greenhouse->config['provider']);
        $this->assertSame('workable', $workable->config['provider']);
        $this->assertTrue($greenhouse->is_active);
        $this->assertTrue($workable->is_active);
    }
}
