<?php

declare(strict_types=1);

namespace App\Services\Candidate;

use App\Models\CandidateProfile;

class ProfileStrengthCalculator
{
    public function calculate(CandidateProfile $profile): int
    {
        $weights = config('candidate.profile_strength.weights');
        $maxScore = (int) config('candidate.profile_strength.max_score', 100);
        $score = 0;

        if (filled($profile->headline)) {
            $score += (int) $weights['headline'];
        }

        if (filled($profile->summary)) {
            $score += (int) $weights['summary'];
        }

        if (filled($profile->city)) {
            $score += (int) $weights['city'];
        }

        if (filled($profile->country)) {
            $score += (int) $weights['country'];
        }

        if (filled($profile->desired_position)) {
            $score += (int) $weights['desired_position'];
        }

        if ($profile->work_preference !== null) {
            $score += (int) $weights['work_preference'];
        }

        if ($profile->years_of_experience !== null) {
            $score += (int) $weights['years_of_experience'];
        }

        if (filled($profile->linkedin_url)) {
            $score += (int) $weights['linkedin_url'];
        }

        if (($profile->experiences_count ?? $profile->experiences()->count()) > 0) {
            $score += (int) $weights['experience'];
        }

        if (($profile->educations_count ?? $profile->educations()->count()) > 0) {
            $score += (int) $weights['education'];
        }

        if (($profile->skills_count ?? $profile->skills()->count()) > 0) {
            $score += (int) $weights['skill'];
        }

        if (filled($profile->cv_file_path)) {
            $score += (int) $weights['cv'];
        }

        return min($score, $maxScore);
    }
}
