<?php

declare(strict_types=1);

namespace App\Http\Resources\Candidate;

use App\Models\CandidateExperience;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CandidateExperience */
class CandidateExperienceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'position_title' => $this->position_title,
            'employment_type' => $this->employment_type?->value,
            'location' => $this->location,
            'is_current' => $this->is_current,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'description' => $this->description,
        ];
    }
}
