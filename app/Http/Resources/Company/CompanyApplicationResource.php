<?php

declare(strict_types=1);

namespace App\Http\Resources\Company;

use App\Http\Resources\Candidate\ApplicationStatusHistoryResource;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Application */
class CompanyApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'candidate_profile_id' => $this->candidate_profile_id,
            'status' => $this->status->value,
            'cover_letter' => $this->cover_letter,
            'match_score' => $this->match_score,
            'trust_score' => $this->trust_score,
            'resume_snapshot_path' => $this->resume_snapshot_path,
            'applied_at' => $this->applied_at?->toIso8601String(),
            'status_updated_at' => $this->status_updated_at?->toIso8601String(),
            'job' => $this->whenLoaded('job', fn () => $this->job === null ? null : [
                'id' => $this->job->id,
                'title' => $this->job->title,
                'slug' => $this->job->slug,
                'city' => $this->job->city,
                'country' => $this->job->country,
                'status' => $this->job->status->value,
            ]),
            'candidate' => $this->whenLoaded(
                'candidateProfile',
                fn () => (new CompanyApplicationCandidateResource($this->candidateProfile))->resolve(),
            ),
            'status_history' => ApplicationStatusHistoryResource::collection(
                $this->whenLoaded('statusHistory'),
            ),
        ];
    }
}
