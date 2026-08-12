<?php

declare(strict_types=1);

namespace App\Services\Candidate;

use App\Services\Candidate\CvParsing\CvSectionDetector;
use App\Services\Candidate\CvParsing\CvTextExtractor;

class CvParserService
{
    public function __construct(
        private readonly CvTextExtractor $textExtractor,
        private readonly CvSectionDetector $sectionDetector,
    ) {}

    /**
     * @return array{
     *     text: string,
     *     sections: array<string, string>,
     *     source_filename: string,
     *     parsed_at: string,
     *     parser_version: string
     * }
     */
    public function parse(string $absolutePath, string $sourceFilename): array
    {
        $extension = strtolower(pathinfo($sourceFilename, PATHINFO_EXTENSION));
        $text = $this->textExtractor->extract($absolutePath, $extension);

        return [
            'text' => $text,
            'sections' => $this->sectionDetector->detect($text),
            'source_filename' => $sourceFilename,
            'parsed_at' => now()->toIso8601String(),
            'parser_version' => (string) config('candidate.parser_version'),
        ];
    }
}
