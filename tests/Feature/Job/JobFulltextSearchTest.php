<?php

namespace Tests\Feature\Job;

use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobFulltextSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * MySQL FULLTEXT cannot reliably match rows inserted inside the same transaction.
     *
     * @var list<string>
     */
    protected array $connectionsToTransact = [];

    #[Test]
    public function keyword_fulltext_search_uses_mysql_fulltext_index(): void
    {
        $user = User::factory()->company()->create([
            'email' => 'job-fulltext-search@example.com',
        ]);
        $company = Company::factory()->create([
            'user_id' => $user->id,
        ]);

        $jobAttributes = [
            'company_id' => $company->id,
            'posted_by' => $user->id,
        ];

        Job::factory()->published()->create(array_merge($jobAttributes, [
            'title' => 'Laravel Backend Engineer',
            'description' => 'Build scalable Laravel applications for enterprise customers.',
        ]));
        Job::factory()->published()->create(array_merge($jobAttributes, [
            'title' => 'Frontend React Developer',
            'description' => 'Build user interfaces with React and TypeScript.',
        ]));

        $this->getJson('/api/v1/jobs?keyword=Laravel')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Laravel Backend Engineer');
    }
}
