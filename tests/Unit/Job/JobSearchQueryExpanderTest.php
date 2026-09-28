<?php

declare(strict_types=1);

namespace Tests\Unit\Job;

use App\Services\Job\JobSearchQueryExpander;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobSearchQueryExpanderTest extends TestCase
{
    private JobSearchQueryExpander $expander;

    protected function setUp(): void
    {
        parent::setUp();
        $this->expander = new JobSearchQueryExpander;
    }

    #[Test]
    public function expands_qa_to_quality_assurance_equivalents(): void
    {
        $expanded = $this->expander->expand('QA');

        $this->assertTrue($expanded['expanded']);
        $this->assertContains('Quality Assurance', $expanded['phrases']);
        $this->assertContains('Test Engineer', $expanded['phrases']);
        $this->assertLessThanOrEqual(8, count($expanded['phrases']));
    }

    #[Test]
    public function expands_frontend_variants(): void
    {
        $expanded = $this->expander->expand('frontend');

        $this->assertTrue($expanded['expanded']);
        $joined = mb_strtolower(implode(' ', $expanded['phrases']));
        $this->assertTrue(str_contains($joined, 'front-end') || str_contains($joined, 'front end'));
    }

    #[Test]
    public function expands_fullstack_variants(): void
    {
        $expanded = $this->expander->expand('fullstack');

        $this->assertTrue($expanded['expanded']);
        $joined = mb_strtolower(implode(' ', $expanded['phrases']));
        $this->assertTrue(str_contains($joined, 'full stack') || str_contains($joined, 'full-stack'));
    }

    #[Test]
    public function expands_devops_to_sre_and_platform(): void
    {
        $expanded = $this->expander->expand('DevOps');

        $this->assertTrue($expanded['expanded']);
        $joined = mb_strtolower(implode(' ', $expanded['phrases']));
        $this->assertTrue(str_contains($joined, 'sre') || str_contains($joined, 'site reliability') || str_contains($joined, 'platform engineer'));
    }

    #[Test]
    public function expands_yazilim_ascii_to_turkish(): void
    {
        $expanded = $this->expander->expand('Yazilim');

        $this->assertTrue($expanded['expanded']);
        $this->assertTrue(
            in_array('Yazılım', $expanded['phrases'], true)
            || in_array('yazılım', $expanded['phrases'], true)
        );
    }

    #[Test]
    public function does_not_expand_unrelated_exact_queries(): void
    {
        $expanded = $this->expander->expand('Laravel');

        $this->assertFalse($expanded['expanded']);
        $this->assertSame(['Laravel'], $expanded['phrases']);
    }

    #[Test]
    public function boolean_query_quotes_phrases_and_is_bounded(): void
    {
        $boolean = $this->expander->toBooleanFulltext('QA');

        $this->assertStringContainsString('"Quality Assurance"', $boolean);
        $this->assertDoesNotMatchRegularExpression('/[+\-<>()~*]/', str_replace('"', '', $boolean));
    }

    #[Test]
    public function location_variants_include_istanbul_ascii_and_turkish(): void
    {
        $variants = $this->expander->locationVariants('İstanbul');

        $this->assertContains('İstanbul', $variants);
        $this->assertContains('Istanbul', $variants);
    }
}
