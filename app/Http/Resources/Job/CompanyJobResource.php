<?php

declare(strict_types=1);

namespace App\Http\Resources\Job;

use App\Enums\SkillImportance;
use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Job */
class CompanyJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'requirements' => $this->requirements,
            'responsibilities' => $this->responsibilities,
            'category' => $this->category,
            'employment_type' => $this->employment_type?->value,
            'work_type' => $this->work_type?->value,
            'experience_level' => $this->experience_level?->value,
            'city' => $this->city,
            'country' => $this->country,
            'salary_min' => $this->salary_min,
            'salary_max' => $this->salary_max,
            'salary_currency' => $this->salary_currency,
            'is_salary_visible' => $this->is_salary_visible,
            'application_deadline' => $this->application_deadline?->toDateString(),
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'status' => $this->status->value,
            'source' => $this->source->value,
            'trust_score' => $this->trust_score,
            'trust_label' => $this->trust_label->value,
            'trust_analysis_status' => $this->trust_analysis_status->value,
            'applications_count' => $this->applications_count,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company?->id,
                'name' => $this->company?->name,
                'slug' => $this->company?->slug,
            ]),
            'source_provider' => $this->whenLoaded('sourceProvider', fn () => $this->sourceProvider === null ? null : [
                'id' => $this->sourceProvider->id,
                'name' => $this->sourceProvider->name,
                'type' => $this->sourceProvider->type->value,
            ]),
            'skills' => $this->whenLoaded('skills', fn () => $this->skills->map(fn ($skill) => [
                'id' => $skill->id,
                'name' => $skill->name,
                'slug' => $skill->slug,
                'importance' => $skill->pivot->importance instanceof SkillImportance
                    ? $skill->pivot->importance->value
                    : $skill->pivot->importance,
            ])),
        ];
    }
}
