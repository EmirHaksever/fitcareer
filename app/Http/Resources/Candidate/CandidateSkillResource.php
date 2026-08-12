<?php

declare(strict_types=1);

namespace App\Http\Resources\Candidate;

use App\Models\CandidateSkill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CandidateSkill */
class CandidateSkillResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'skill_id' => $this->skill_id,
            'proficiency_level' => $this->proficiency_level?->value,
            'years_of_experience' => $this->years_of_experience,
            'skill' => new SkillResource($this->whenLoaded('skill')),
        ];
    }
}
