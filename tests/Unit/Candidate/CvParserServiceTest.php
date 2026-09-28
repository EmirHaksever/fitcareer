<?php

namespace Tests\Unit\Candidate;

use App\Services\Candidate\CvParserService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class CvParserServiceTest extends TestCase
{
    #[Test]
    public function it_parses_docx_with_expected_structure(): void
    {
        $path = $this->createDocxFixture("Summary\nJane Doe\nExperience\nAcme Corp");

        $result = app(CvParserService::class)->parse($path, 'resume.docx');

        $this->assertArrayHasKey('text', $result);
        $this->assertArrayHasKey('sections', $result);
        $this->assertSame('resume.docx', $result['source_filename']);
        $this->assertSame(config('candidate.parser_version'), $result['parser_version']);
        $this->assertNotEmpty($result['parsed_at']);
        $this->assertStringContainsString('Jane Doe', $result['text']);
        $this->assertArrayHasKey('summary', $result['sections']);
    }

    private function createDocxFixture(string $text): string
    {
        $path = storage_path('framework/testing/parser-test.docx');
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString(
            'word/document.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body><w:p><w:r><w:t>'.htmlspecialchars($text, ENT_XML1).'</w:t></w:r></w:p></w:body></w:document>',
        );
        $zip->close();

        return $path;
    }
}
