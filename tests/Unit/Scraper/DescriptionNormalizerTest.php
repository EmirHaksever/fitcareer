<?php

declare(strict_types=1);

namespace Tests\Unit\Scraper;

use App\Services\Scraper\DescriptionNormalizerService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DescriptionNormalizerTest extends TestCase
{
    private DescriptionNormalizerService $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new DescriptionNormalizerService;
    }

    #[Test]
    public function decodes_nbsp_entity_to_whitespace(): void
    {
        $this->assertSame('Hello world', $this->normalizer->normalize('Hello&nbsp;world'));
    }

    #[Test]
    public function decodes_amp_entity(): void
    {
        $this->assertSame('Tom & Jerry', $this->normalizer->normalize('Tom &amp; Jerry'));
    }

    #[Test]
    public function strips_html_tags_after_entity_decode(): void
    {
        $this->assertSame('Bold text', $this->normalizer->normalize('&lt;p&gt;&lt;strong&gt;Bold&lt;/strong&gt; text&lt;/p&gt;'));
    }

    #[Test]
    public function normalizes_whitespace(): void
    {
        $this->assertSame('Line one Line two', $this->normalizer->normalize("Line one\n\n  Line two"));
    }

    #[Test]
    public function preserves_meaningful_plain_text(): void
    {
        $input = 'Build AI-powered products for global clients.';

        $this->assertSame($input, $this->normalizer->normalize($input));
    }

    #[Test]
    public function handles_null_and_empty_strings(): void
    {
        $this->assertSame('', $this->normalizer->normalize(null));
        $this->assertSame('', $this->normalizer->normalize(''));
    }
}
