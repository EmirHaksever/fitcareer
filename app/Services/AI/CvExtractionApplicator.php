<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Enums\WorkPreference;
use App\Models\CandidateExperience;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Services\AI\DTO\CvExtractionResult;
use Illuminate\Support\Carbon;

class CvExtractionApplicator
{
    public function __construct(
        private readonly CvSkillCatalogMatcher $skillCatalogMatcher,
    ) {}

    /**
     * @return array{
     *     skills_attached: list<string>,
     *     skills_skipped: list<string>,
     *     skills_unmatched: list<string>,
     *     profile_fields_updated: list<string>,
     *     experiences_created: int
     * }
     */
    public function apply(CandidateProfile $profile, CvExtractionResult $extraction): array
    {
        $summary = [
            'skills_attached' => [],
            'skills_skipped' => [],
            'skills_unmatched' => [],
            'profile_fields_updated' => [],
            'experiences_created' => 0,
        ];

        $profile->loadMissing(['candidateSkills']);

        $existingSkillIds = $profile->candidateSkills
            ->pluck('skill_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $skillMatches = $this->skillCatalogMatcher->matchMany($extraction->skillNames());

        foreach ($skillMatches['matched'] as $match) {
            $skill = $match['skill'];

            if (in_array($skill->id, $existingSkillIds, true)) {
                $summary['skills_skipped'][] = $skill->name;

                continue;
            }

            CandidateSkill::query()->create([
                'candidate_profile_id' => $profile->id,
                'skill_id' => $skill->id,
            ]);

            $existingSkillIds[] = $skill->id;
            $summary['skills_attached'][] = $skill->name;
        }

        $summary['skills_unmatched'] = $skillMatches['unmatched'];

        $profileUpdates = [];

        if ($extraction->totalExperienceYears !== null && $profile->years_of_experience === null) {
            $profileUpdates['years_of_experience'] = $extraction->totalExperienceYears;
            $summary['profile_fields_updated'][] = 'years_of_experience';
        }

        $location = $this->parseLocation($extraction->location);

        if ($location['city'] !== null && ($profile->city === null || $profile->city === '')) {
            $profileUpdates['city'] = $location['city'];
            $summary['profile_fields_updated'][] = 'city';
        }

        if ($location['country'] !== null && ($profile->country === null || $profile->country === '')) {
            $profileUpdates['country'] = $location['country'];
            $summary['profile_fields_updated'][] = 'country';
        }

        $workPreference = $this->resolveWorkPreference($extraction->workPreferences);

        if ($workPreference !== null && $profile->work_preference === null) {
            $profileUpdates['work_preference'] = $workPreference;
            $summary['profile_fields_updated'][] = 'work_preference';
        }

        if ($profileUpdates !== []) {
            $profile->fill($profileUpdates);
            $profile->save();
        }

        foreach ($extraction->experience as $item) {
            if ($item->company === null) {
                continue;
            }

            $duplicateExists = CandidateExperience::query()
                ->where('candidate_profile_id', $profile->id)
                ->where('company_name', $item->company)
                ->where('position_title', $item->title)
                ->exists();

            if ($duplicateExists) {
                continue;
            }

            $years = $item->years ?? 1;
            $startDate = Carbon::now()->subYears(max(1, $years))->startOfYear();

            CandidateExperience::query()->create([
                'candidate_profile_id' => $profile->id,
                'company_name' => $item->company,
                'position_title' => $item->title,
                'start_date' => $startDate->toDateString(),
                'is_current' => false,
            ]);

            $summary['experiences_created']++;
        }

        return $summary;
    }

    /**
     * @param  list<string>  $preferences
     */
    private function resolveWorkPreference(array $preferences): ?WorkPreference
    {
        foreach ($preferences as $preference) {
            $normalized = mb_strtolower(trim($preference));

            $match = match (true) {
                str_contains($normalized, 'remote') => WorkPreference::Remote,
                str_contains($normalized, 'hybrid') => WorkPreference::Hybrid,
                str_contains($normalized, 'onsite') || str_contains($normalized, 'office') => WorkPreference::Onsite,
                default => null,
            };

            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }

    /**
     * @return array{city: ?string, country: ?string}
     */
    private function parseLocation(?string $location): array
    {
        if ($location === null || trim($location) === '') {
            return ['city' => null, 'country' => null];
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $location))));

        if (count($parts) >= 2) {
            return [
                'city' => $parts[0],
                'country' => $parts[count($parts) - 1],
            ];
        }

        return ['city' => $parts[0], 'country' => null];
    }
}
