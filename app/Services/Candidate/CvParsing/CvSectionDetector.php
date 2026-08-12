<?php

declare(strict_types=1);

namespace App\Services\Candidate\CvParsing;

class CvSectionDetector
{
    /**
     * @return array<string, string>
     */
    public function detect(string $text): array
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $sections = [];
        $currentKey = 'summary';
        $buffer = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            $heading = $this->matchHeading($trimmed);

            if ($heading !== null) {
                if ($buffer !== []) {
                    $sections[$currentKey] = trim(implode("\n", $buffer));
                }

                $currentKey = $heading;
                $buffer = [];

                continue;
            }

            $buffer[] = $trimmed;
        }

        if ($buffer !== []) {
            $sections[$currentKey] = trim(implode("\n", $buffer));
        }

        return $sections;
    }

    private function matchHeading(string $line): ?string
    {
        $normalized = mb_strtolower($line);

        $patterns = [
            'experience' => '/^(work\s+)?experience|employment\s+history|professional\s+experience|iş\s+deneyimleri?|deneyim$/u',
            'education' => '/^education|academic\s+background|eğitim(\s+bilgileri)?$/u',
            'skills' => '/^(technical\s+)?skills|competencies|yetenekler|beceriler$/u',
            'certifications' => '/^certifications?|licenses?|sertifikalar$/u',
            'projects' => '/^projects?|portfolios?|projeler$/u',
            'summary' => '/^(professional\s+)?summary|profile|objective|özet|hakkımda$/u',
            'contact' => '/^contact(\s+info)?|iletişim$/u',
        ];

        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return $key;
            }
        }

        return null;
    }
}
