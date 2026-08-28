<?php

declare(strict_types=1);

namespace App\Http\Resources\Company;

use App\Http\Resources\Candidate\ApplicationStatusHistoryResource;
use App\Models\Application;
use App\Support\JobScorePresenter;
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
        $includeDetails = $request->route('application') !== null;
        $match = $this->job === null
            ? [
                'match_analysis_status' => $this->match_score !== null ? 'completed' : null,
                'match_details' => null,
            ]
            : JobScorePresenter::companyMatchFields(
                $this->job,
                $this->candidate_profile_id,
                $this->match_score,
                $includeDetails,
            );

        return [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'candidate_profile_id' => $this->candidate_profile_id,
            'status' => $this->status->value,
            'cover_letter' => $this->cover_letter,
            'match_score' => $this->match_score,
            'match_analysis_status' => $match['match_analysis_status'],
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
                'experience_level' => $this->job->experience_level?->value,
                'employment_type' => $this->job->employment_type?->value,
                'work_type' => $this->job->work_type?->value,
            ]),
            'candidate' => $this->whenLoaded(
                'candidateProfile',
                fn () => (new CompanyApplicationCandidateResource($this->candidateProfile))->resolve(),
            ),
            'status_history' => ApplicationStatusHistoryResource::collection(
                $this->whenLoaded('statusHistory'),
            ),
            'match_details' => $this->when($includeDetails, $match['match_details']),
        ];
    }
}
