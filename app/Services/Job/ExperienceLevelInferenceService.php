<?php

declare(strict_types=1);

namespace App\Services\Job;

use App\Enums\ExperienceLevel;

final class ExperienceLevelInferenceService
{
    public function inferFromTitle(string $title): ?ExperienceLevel
    {
        $normalized = $this->normalize($title);

        if ($normalized === '') {
            return null;
        }

        if ($this->containsAny($normalized, ['intern', 'internship', 'stajyer', 'staj', 'staji'])) {
            return ExperienceLevel::Intern;
        }

        if ($this->containsAny($normalized, [
            'junior',
            'jr',
            'entry level',
            'entrylevel',
            'new grad',
            'newgrad',
            'graduate program',
            'trainee',
            'associate',
            'yeni mezun',
            'yenimezun',
        ])) {
            return ExperienceLevel::Entry;
        }

        if ($this->containsAny($normalized, [
            'director',
            'direktor',
            'direktoru',
            'vice president',
            'vp',
            'chief',
        ])) {
            return ExperienceLevel::Executive;
        }

        if ($this->containsAny($normalized, [
            'lead',
            'principal',
            'staff',
            'manager',
            'mudur',
            'head of',
            'headof',
        ])) {
            return ExperienceLevel::Lead;
        }

        if ($this->containsAny($normalized, ['senior', 'sr', 'kidemli'])) {
            return ExperienceLevel::Senior;
        }

        if ($this->containsAny($normalized, ['mid level', 'midlevel', 'intermediate', 'mid'])) {
            return ExperienceLevel::Mid;
        }

        return null;
    }

    private function normalize(string $title): string
    {
        $title = mb_strtolower(trim($title));
        if ($title === '') {
            return '';
        }

        $title = str_replace(
            ['ı', 'i̇', 'ş', 'ğ', 'ü', 'ö', 'ç'],
            ['i', 'i', 's', 'g', 'u', 'o', 'c'],
            $title,
        );

        $title = str_replace(['.', ',', '/', '|', '-', '_', '(', ')', ':'], ' ', $title);
        $title = preg_replace('/\s+/', ' ', $title) ?? $title;

        return trim($title);
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $normalized, array $needles): bool
    {
        $haystack = ' '.$normalized.' ';

        foreach ($needles as $needle) {
            if (str_contains($haystack, ' '.$needle.' ')) {
                return true;
            }
        }

        return false;
    }
}
