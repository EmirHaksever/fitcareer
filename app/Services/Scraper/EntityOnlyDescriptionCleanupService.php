<?php

declare(strict_types=1);

namespace App\Services\Scraper;

class EntityOnlyDescriptionCleanupService
{
    public function normalize(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return str_replace("\xc2\xa0", ' ', $decoded);
    }
}
