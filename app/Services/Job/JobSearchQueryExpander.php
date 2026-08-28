<?php

declare(strict_types=1);

namespace App\Services\Job;

final class JobSearchQueryExpander
{
    private const MAX_PHRASES = 8;

    /**
     * @var list<array{triggers: list<string>, phrases: list<string>}>
     */
    private const GROUPS = [
        [
            'triggers' => ['qa', 'sdet'],
            'phrases' => ['QA', 'Quality Assurance', 'Quality Assurance Engineer', 'Test Engineer', 'Software Tester', 'Test Automation', 'Quality Engineer'],
        ],
        [
            'triggers' => ['frontend', 'front end', 'front-end'],
            'phrases' => ['Frontend', 'Front End', 'Front-End', 'Frontend Developer', 'Frontend Engineer', 'UI Developer'],
        ],
        [
            'triggers' => ['backend', 'back end', 'back-end'],
            'phrases' => ['Backend', 'Back End', 'Back-End', 'Backend Developer', 'Backend Engineer', 'Server-side'],
        ],
        [
            'triggers' => ['fullstack', 'full stack', 'full-stack'],
            'phrases' => ['Fullstack', 'Full Stack', 'Full-Stack'],
        ],
        [
            'triggers' => ['android', 'ios', 'flutter', 'react native'],
            'phrases' => ['Mobile Developer', 'Android', 'iOS', 'Flutter', 'React Native'],
        ],
        [
            'triggers' => ['devops', 'dev ops', 'sre'],
            'phrases' => ['DevOps', 'Dev Ops', 'Platform Engineer', 'Site Reliability Engineer', 'SRE', 'Cloud Engineer'],
        ],
        [
            'triggers' => ['yazilim'],
            'phrases' => ['Yazılım', 'Yazilim'],
        ],
    ];

    /**
     * @return array{expanded: bool, phrases: list<string>}
     */
    public function expand(string $keyword): array
    {
        $original = trim($keyword);
        if ($original === '') {
            return ['expanded' => false, 'phrases' => []];
        }

        $folded = $this->fold($original);
        $phrases = [$original];

        foreach (self::GROUPS as $group) {
            if (! $this->matchesGroup($folded, $group['triggers'])) {
                continue;
            }

            foreach ($group['phrases'] as $phrase) {
                if (! $this->phraseExists($phrases, $phrase)) {
                    $phrases[] = $phrase;
                }
            }
        }

        $phrases = array_slice($phrases, 0, self::MAX_PHRASES);

        return [
            'expanded' => count($phrases) > 1,
            'phrases' => $phrases,
        ];
    }

    public function toBooleanFulltext(string $keyword): string
    {
        $expanded = $this->expand($keyword);
        $parts = [];

        foreach ($expanded['phrases'] as $phrase) {
            $safe = str_replace('"', '', $phrase);
            $safe = trim($safe);
            if ($safe === '') {
                continue;
            }
            $parts[] = '"'.$safe.'"';
        }

        return implode(' ', $parts);
    }

    /**
     * @return list<string>
     */
    public function locationVariants(string $location): array
    {
        $original = trim($location);
        if ($original === '') {
            return [];
        }

        $variants = [$original];
        $folded = $this->fold($original);

        if (str_contains($folded, 'istanbul')) {
            foreach (['Istanbul', 'İstanbul'] as $variant) {
                if (! in_array($variant, $variants, true)) {
                    $variants[] = $variant;
                }
            }
        }

        return $variants;
    }

    public function shouldUseBooleanMode(string $keyword): bool
    {
        $expanded = $this->expand($keyword);
        if ($expanded['expanded']) {
            return true;
        }

        foreach (preg_split('/\s+/', trim($keyword)) ?: [] as $token) {
            if (mb_strlen($token) > 0 && mb_strlen($token) < 4) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $triggers
     */
    private function matchesGroup(string $foldedQuery, array $triggers): bool
    {
        foreach ($triggers as $trigger) {
            if ($foldedQuery === $this->fold($trigger)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $phrases
     */
    private function phraseExists(array $phrases, string $candidate): bool
    {
        foreach ($phrases as $phrase) {
            if (mb_strtolower($phrase) === mb_strtolower($candidate)) {
                return true;
            }
        }

        return false;
    }

    private function fold(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = str_replace(['ı', 'i̇', 'ş', 'ğ', 'ü', 'ö', 'ç'], ['i', 'i', 's', 'g', 'u', 'o', 'c'], $text);
        $text = str_replace(['-', '_'], ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }
}
