<?php

declare(strict_types=1);

namespace App\Http\Resources\Job;

use App\Models\Job;
use App\Support\JobScorePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Job */
class JobListResource extends JsonResource
{
    public function __construct(
        $resource,
        private readonly ?int $candidateProfileId = null,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $trust = JobScorePresenter::trustFields($this->resource);
        $fit = JobScorePresenter::fitFields($this->resource, $this->candidateProfileId);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'category' => $this->category,
            'employment_type' => $this->employment_type?->value,
            'work_type' => $this->work_type?->value,
            'experience_level' => $this->experience_level?->value,
            'city' => $this->city,
            'country' => $this->country,
            'salary_min' => $this->when($this->is_salary_visible, $this->salary_min),
            'salary_max' => $this->when($this->is_salary_visible, $this->salary_max),
            'salary_currency' => $this->when($this->is_salary_visible, $this->salary_currency),
            'is_salary_visible' => $this->is_salary_visible,
            'published_at' => $this->published_at?->toIso8601String(),
            'source' => $this->source->value,
            'source_company_name' => $this->source_company_name,
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company?->id,
                'name' => $this->company?->name,
                'slug' => $this->company?->slug,
                'is_verified' => (bool) $this->company?->is_verified,
            ]),
            'source_provider' => $this->whenLoaded('sourceProvider', fn () => $this->sourceProvider === null ? null : [
                'id' => $this->sourceProvider->id,
                'name' => $this->sourceProvider->name,
                'type' => $this->sourceProvider->type->value,
            ]),
            ...$trust,
            ...$fit,
        ];
    }
}
