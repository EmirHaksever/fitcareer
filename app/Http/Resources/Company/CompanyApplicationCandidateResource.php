<?php

declare(strict_types=1);

namespace App\Http\Resources\Company;

use App\Models\CandidateProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CandidateProfile */
class CompanyApplicationCandidateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'headline' => $this->headline,
            'city' => $this->city,
            'country' => $this->country,
            'years_of_experience' => $this->years_of_experience,
            'profile_strength_score' => $this->profile_strength_score,
            'user' => $this->whenLoaded('user', fn () => $this->user === null ? null : [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
        ];
    }
}
