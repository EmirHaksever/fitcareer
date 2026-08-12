<?php

declare(strict_types=1);

namespace App\Http\Resources\Candidate;

use App\Models\CandidateProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CandidateProfile */
class CandidateProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'headline' => $this->headline,
            'summary' => $this->summary,
            'city' => $this->city,
            'country' => $this->country,
            'profile_photo_path' => $this->profile_photo_path,
            'has_cv' => $this->cv_file_path !== null,
            'profile_strength_score' => $this->profile_strength_score,
            'open_to_work' => $this->open_to_work,
            'desired_position' => $this->desired_position,
            'desired_salary_min' => $this->desired_salary_min,
            'desired_salary_max' => $this->desired_salary_max,
            'work_preference' => $this->work_preference?->value,
            'years_of_experience' => $this->years_of_experience,
            'linkedin_url' => $this->linkedin_url,
            'github_url' => $this->github_url,
            'portfolio_url' => $this->portfolio_url,
            'experiences' => CandidateExperienceResource::collection($this->whenLoaded('experiences')),
            'educations' => CandidateEducationResource::collection($this->whenLoaded('educations')),
            'certifications' => CandidateCertificationResource::collection($this->whenLoaded('certifications')),
            'projects' => CandidateProjectResource::collection($this->whenLoaded('projects')),
            'skills' => CandidateSkillResource::collection($this->whenLoaded('candidateSkills')),
        ];
    }
}
