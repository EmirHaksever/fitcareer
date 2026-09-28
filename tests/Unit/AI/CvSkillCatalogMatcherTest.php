<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\Models\Skill;
use App\Services\AI\CvSkillCatalogMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CvSkillCatalogMatcherTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $e2eSkillNames = [
        'Flutter',
        'Dart',
        'Firebase',
        'Supabase',
        'REST API',
        'Git',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ($this->e2eSkillNames as $name) {
            Skill::factory()->create([
                'name' => $name,
                'slug' => Str::slug($name),
                'category' => 'Technology',
            ]);
        }

        Skill::factory()->create(['name' => 'Ut', 'slug' => 'ut', 'category' => 'general']);
        Skill::factory()->create(['name' => 'Ab', 'slug' => 'ab', 'category' => 'general']);
        Skill::factory()->create(['name' => 'Est', 'slug' => 'est', 'category' => 'general']);
    }

    #[Test]
    public function it_exact_matches_e2e_catalog_skills(): void
    {
        $matcher = app(CvSkillCatalogMatcher::class);
        $catalog = Skill::query()->get();

        foreach ($this->e2eSkillNames as $name) {
            $matched = $matcher->match($name, $catalog);
            $this->assertNotNull($matched, "Expected match for {$name}");
            $this->assertSame($name, $matched->name);
        }
    }

    #[Test]
    public function it_matches_case_insensitively_and_trims_whitespace(): void
    {
        $matcher = app(CvSkillCatalogMatcher::class);
        $catalog = Skill::query()->get();

        $this->assertSame('Flutter', $matcher->match('  flutter  ', $catalog)?->name);
        $this->assertSame('Git', $matcher->match('git', $catalog)?->name);
    }

    #[Test]
    public function it_does_not_false_positive_match_short_junk_catalog_entries(): void
    {
        $matcher = app(CvSkillCatalogMatcher::class);
        $catalog = Skill::query()->get();

        $this->assertSame('Flutter', $matcher->match('Flutter', $catalog)?->name);
        $this->assertNotSame('Ut', $matcher->match('Flutter', $catalog)?->name);

        $this->assertSame('Supabase', $matcher->match('Supabase', $catalog)?->name);
        $this->assertNotSame('Ab', $matcher->match('Supabase', $catalog)?->name);

        $this->assertSame('REST API', $matcher->match('REST API', $catalog)?->name);
        $this->assertNotSame('Est', $matcher->match('REST API', $catalog)?->name);
    }

    #[Test]
    public function it_returns_unmatched_for_unknown_skills(): void
    {
        $matcher = app(CvSkillCatalogMatcher::class);

        $this->assertNull($matcher->match('GraphQL', Skill::all()));
    }

    #[Test]
    public function it_prefers_exact_git_match_over_other_catalog_entries(): void
    {
        $matcher = app(CvSkillCatalogMatcher::class);
        $git = Skill::query()->where('name', 'Git')->firstOrFail();

        $this->assertSame($git->id, $matcher->match('Git', Skill::all())?->id);
    }

    #[Test]
    public function it_returns_matched_and_unmatched_lists(): void
    {
        $result = app(CvSkillCatalogMatcher::class)->matchMany(['Dart', 'GraphQL']);

        $this->assertCount(1, $result['matched']);
        $this->assertSame('Dart', $result['matched'][0]['skill']->name);
        $this->assertSame(['GraphQL'], $result['unmatched']);
    }

    #[Test]
    public function it_resolves_restful_api_alias_to_rest_api(): void
    {
        $matcher = app(CvSkillCatalogMatcher::class);

        $this->assertSame('REST API', $matcher->match('RESTful API', Skill::all())?->name);
    }
}
