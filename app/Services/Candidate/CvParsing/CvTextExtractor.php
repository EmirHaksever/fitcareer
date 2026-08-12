<?php

declare(strict_types=1);

namespace App\Services\Candidate\CvParsing;

use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

class CvTextExtractor
{
    public function extract(string $absolutePath, string $extension): string
    {
        return match (strtolower($extension)) {
            'pdf' => $this->extractFromPdf($absolutePath),
            'docx' => $this->extractFromDocx($absolutePath),
            'doc' => $this->extractFromDoc($absolutePath),
            default => throw new \InvalidArgumentException('Unsupported CV file type.'),
        };
    }

    private function extractFromPdf(string $absolutePath): string
    {
        $parser = new PdfParser;
        $pdf = $parser->parseFile($absolutePath);

        return $this->normalizeText($pdf->getText());
    }

    private function extractFromDocx(string $absolutePath): string
    {
        $zip = new ZipArchive;

        if ($zip->open($absolutePath) !== true) {
            throw new \RuntimeException('Unable to open DOCX archive.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new \RuntimeException('DOCX document body not found.');
        }

        $text = strip_tags(str_replace(['</w:p>', '</w:tr>'], "\n", $xml));

        return $this->normalizeText($text);
    }

    private function extractFromDoc(string $absolutePath): string
    {
        $binary = file_get_contents($absolutePath);

        if ($binary === false) {
            throw new \RuntimeException('Unable to read DOC file.');
        }

        $chunks = [];

        if (preg_match_all('/[\x20-\x7E]{4,}/', $binary, $asciiMatches)) {
            $chunks = array_merge($chunks, $asciiMatches[0]);
        }

        if (preg_match_all('/(?:[\x20-\x7E]\x00){4,}/', $binary, $utf16Matches)) {
            foreach ($utf16Matches[0] as $match) {
                $decoded = mb_convert_encoding($match, 'UTF-8', 'UTF-16LE');
                if ($decoded !== false) {
                    $chunks[] = $decoded;
                }
            }
        }

        return $this->normalizeText(implode("\n", $chunks));
    }

    private function normalizeText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[^\S\n]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
