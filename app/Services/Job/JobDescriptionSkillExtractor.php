<?php

declare(strict_types=1);

namespace App\Services\Job;

use App\Models\Skill;
use Illuminate\Database\Eloquent\Collection;

class JobDescriptionSkillExtractor
{
    /**
     * @return Collection<int, Skill>
     */
    public function extract(string $description): Collection
    {
        $normalizedDescription = mb_strtolower($description);
        $catalog = Skill::query()
            ->orderByRaw('CHAR_LENGTH(name) DESC')
            ->get();

        /** @var Collection<int, Skill> $matched */
        $matched = new Collection;
        $usedIds = [];

        foreach ($catalog as $skill) {
            if ($this->containsSkill($normalizedDescription, $skill->name)) {
                if (in_array($skill->id, $usedIds, true)) {
                    continue;
                }

                $usedIds[] = $skill->id;
                $matched->push($skill);
            }
        }

        return $matched;
    }

    private function containsSkill(string $normalizedDescription, string $skillName): bool
    {
        $normalizedName = mb_strtolower(trim($skillName));

        if ($normalizedName === '' || mb_strlen($normalizedName) < 2) {
            return false;
        }

        $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($normalizedName, '/').'(?![\p{L}\p{N}])/u';

        return preg_match($pattern, $normalizedDescription) === 1;
    }
}
