<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Skill;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class CvSkillCatalogMatcher
{
    /**
     * Explicit aliases: normalized input => catalog skill name (exact catalog name).
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'restful api' => 'REST API',
        'rest apis' => 'REST API',
    ];

    /**
     * @param  Collection<int, Skill>|list<Skill>  $catalog
     */
    public function match(string $skillName, Collection|array $catalog): ?Skill
    {
        $normalizedInput = $this->normalize($skillName);

        if ($normalizedInput === '') {
            return null;
        }

        $resolvedName = self::ALIASES[$normalizedInput] ?? $skillName;
        $normalizedName = $this->normalize($resolvedName);
        $normalizedSlug = Str::slug($resolvedName);

        $items = $catalog instanceof Collection ? $catalog->all() : $catalog;

        foreach ($items as $item) {
            if ($this->normalize($item->name) === $normalizedName) {
                return $item;
            }
        }

        foreach ($items as $item) {
            if ($item->slug !== '' && $item->slug === $normalizedSlug) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $skillNames
     * @return array{
     *     matched: list<array{input: string, skill: Skill}>,
     *     unmatched: list<string>
     * }
     */
    public function matchMany(array $skillNames): array
    {
        $catalog = Skill::query()->orderBy('name')->get();
        $matched = [];
        $unmatched = [];
        $usedSkillIds = [];

        foreach ($skillNames as $skillName) {
            $skill = $this->match($skillName, $catalog);

            if ($skill === null) {
                $unmatched[] = $skillName;

                continue;
            }

            if (in_array($skill->id, $usedSkillIds, true)) {
                continue;
            }

            $usedSkillIds[] = $skill->id;
            $matched[] = [
                'input' => $skillName,
                'skill' => $skill,
            ];
        }

        return [
            'matched' => $matched,
            'unmatched' => $unmatched,
        ];
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
