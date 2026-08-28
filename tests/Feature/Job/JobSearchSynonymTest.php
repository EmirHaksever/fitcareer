<?php

namespace Tests\Feature\Job;

use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobSearchSynonymTest extends TestCase
{
    use RefreshDatabase;

    /**
     * MySQL FULLTEXT cannot reliably match rows inserted inside the same transaction.
     *
     * @var list<string>
     */
    protected array $connectionsToTransact = [];

    #[Test]
    public function synonym_expansion_discovers_known_equivalents_without_broadening_unrelated_queries(): void
    {
        $user = User::factory()->company()->create([
            'email' => 'job-synonym-search-'.uniqid('', true).'@example.com',
        ]);
        $company = Company::factory()->create([
            'user_id' => $user->id,
        ]);
        $base = [
            'company_id' => $company->id,
            'posted_by' => $user->id,
            'city' => 'Istanbul',
            'country' => 'Turkey',
        ];

        Job::factory()->published()->create(array_merge($base, [
            'title' => 'Quality Assurance Engineer',
            'description' => 'Own test strategy and quality for product releases.',
        ]));
        Job::factory()->published()->create(array_merge($base, [
            'title' => 'Front End Developer',
            'description' => 'Build customer interfaces with accessibility in mind.',
        ]));
        Job::factory()->published()->create(array_merge($base, [
            'title' => 'Full Stack Engineer',
            'description' => 'Deliver features across API and web clients.',
        ]));
        Job::factory()->published()->create(array_merge($base, [
            'title' => 'Site Reliability Engineer',
            'description' => 'Operate production platforms and incident response.',
        ]));
        Job::factory()->published()->create(array_merge($base, [
            'title' => 'Symfony Backend Engineer',
            'description' => 'Build Symfony services for billing workflows.',
        ]));

        $this->getJson('/api/v1/jobs?keyword=QA')
            ->assertOk()
            ->assertJsonPath('data.items.0.title', 'Quality Assurance Engineer');

        $this->getJson('/api/v1/jobs?keyword=frontend')
            ->assertOk()
            ->assertJsonPath('data.items.0.title', 'Front End Developer');

        $this->getJson('/api/v1/jobs?keyword=fullstack')
            ->assertOk()
            ->assertJsonPath('data.items.0.title', 'Full Stack Engineer');

        $devopsTitles = collect($this->getJson('/api/v1/jobs?keyword=DevOps')->json('data.items'))
            ->pluck('title')
            ->all();
        $this->assertContains('Site Reliability Engineer', $devopsTitles);

        $ascii = $this->getJson('/api/v1/jobs?keyword=Engineer&location=Istanbul')
            ->assertOk()
            ->json('data.pagination.total');
        $turkish = $this->getJson('/api/v1/jobs?keyword=Engineer&location=İstanbul')
            ->assertOk()
            ->json('data.pagination.total');
        $this->assertSame($ascii, $turkish);
        $this->assertGreaterThan(0, $ascii);

        $this->getJson('/api/v1/jobs?keyword=Symfony')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Symfony Backend Engineer');
    }
}
