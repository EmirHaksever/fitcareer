<?php

declare(strict_types=1);

namespace App\Services\Scraper;

class DescriptionNormalizerService
{
    public function normalize(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $text = trim($value);

        if ($text === '') {
            return '';
        }

        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = strip_tags($decoded);
        $plain = str_replace("\xc2\xa0", ' ', $plain);
        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;

        return trim($plain);
    }
}
