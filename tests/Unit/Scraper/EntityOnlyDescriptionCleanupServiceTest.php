<?php

declare(strict_types=1);

namespace Tests\Unit\Scraper;

use App\Services\Scraper\EntityOnlyDescriptionCleanupService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EntityOnlyDescriptionCleanupServiceTest extends TestCase
{
    private EntityOnlyDescriptionCleanupService $cleanup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanup = new EntityOnlyDescriptionCleanupService;
    }

    #[Test]
    public function decodes_nbsp_entity_to_normal_space(): void
    {
        $before = 'Hello&nbsp;world';
        $after = $this->cleanup->normalize($before);

        $this->assertSame('Hello world', $after);
        $this->assertSame(substr_count($before, "\n"), substr_count($after, "\n"));
    }

    #[Test]
    public function decodes_utf8_nbsp_to_normal_space(): void
    {
        $before = "Hello\xc2\xa0world";
        $after = $this->cleanup->normalize($before);

        $this->assertSame('Hello world', $after);
        $this->assertSame(substr_count($before, "\n"), substr_count($after, "\n"));
    }

    #[Test]
    public function decodes_amp_entity(): void
    {
        $before = 'Tom &amp; Jerry';
        $after = $this->cleanup->normalize($before);

        $this->assertSame('Tom & Jerry', $after);
        $this->assertSame(substr_count($before, "\n"), substr_count($after, "\n"));
    }

    #[Test]
    public function decodes_multiple_html_entities(): void
    {
        $before = '&lt;strong&gt;Bold&lt;/strong&gt; &amp; &quot;quoted&quot;';
        $after = $this->cleanup->normalize($before);

        $this->assertSame('<strong>Bold</strong> & "quoted"', $after);
        $this->assertSame(substr_count($before, "\n"), substr_count($after, "\n"));
    }

    #[Test]
    public function preserves_single_newlines(): void
    {
        $before = "Line one\nLine two&nbsp;here";

        $after = $this->cleanup->normalize($before);

        $this->assertSame("Line one\nLine two here", $after);
        $this->assertSame(1, substr_count($after, "\n"));
    }

    #[Test]
    public function preserves_double_newlines(): void
    {
        $before = "Paragraph one\n\nParagraph two&nbsp;end";

        $after = $this->cleanup->normalize($before);

        $this->assertSame("Paragraph one\n\nParagraph two end", $after);
        $this->assertSame(2, substr_count($after, "\n"));
    }

    #[Test]
    public function preserves_list_formatting(): void
    {
        $before = "- Item one\n- Item&nbsp;two\n- Item three";

        $after = $this->cleanup->normalize($before);

        $this->assertSame("- Item one\n- Item two\n- Item three", $after);
        $this->assertSame(2, substr_count($after, "\n"));
    }

    #[Test]
    public function leaves_entity_free_text_unchanged(): void
    {
        $before = "Build AI-powered products.\n\nNo entities here.";

        $this->assertSame($before, $this->cleanup->normalize($before));
    }

    #[Test]
    public function handles_null_and_empty_strings(): void
    {
        $this->assertSame('', $this->cleanup->normalize(null));
        $this->assertSame('', $this->cleanup->normalize(''));
    }

    #[Test]
    public function cleans_mixed_entities_while_preserving_paragraph_structure(): void
    {
        $before = "About us&nbsp;\nWe build products &amp; services.\n\nRequirements:\n- SQL&nbsp;skills\n- Tom &amp; Jerry fan";

        $after = $this->cleanup->normalize($before);

        $this->assertSame(
            "About us \nWe build products & services.\n\nRequirements:\n- SQL skills\n- Tom & Jerry fan",
            $after,
        );
        $this->assertSame(substr_count($before, "\n"), substr_count($after, "\n"));
    }

    #[Test]
    public function never_changes_newline_count(): void
    {
        $samples = [
            "A&nbsp;B\nC",
            "Line1\nLine2\nLine3",
            "Para1\n\nPara2&nbsp;x",
            "No entities\nStill\nMultiline",
        ];

        foreach ($samples as $before) {
            $after = $this->cleanup->normalize($before);

            $this->assertSame(
                substr_count($before, "\n"),
                substr_count($after, "\n"),
                'Newline count must be preserved for: '.$before,
            );
        }
    }
}
