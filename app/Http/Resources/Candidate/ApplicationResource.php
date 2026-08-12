<?php

declare(strict_types=1);

namespace App\Http\Resources\Candidate;

use App\Http\Resources\Job\JobListResource;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Application */
class ApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'status' => $this->status->value,
            'cover_letter' => $this->cover_letter,
            'match_score' => $this->match_score,
            'trust_score' => $this->trust_score,
            'resume_snapshot_path' => $this->resume_snapshot_path,
            'applied_at' => $this->applied_at?->toIso8601String(),
            'status_updated_at' => $this->status_updated_at?->toIso8601String(),
            'job' => $this->whenLoaded(
                'job',
                fn () => (new JobListResource($this->job, $this->candidate_profile_id))->resolve(),
            ),
            'status_history' => ApplicationStatusHistoryResource::collection(
                $this->whenLoaded('statusHistory'),
            ),
        ];
    }
}
