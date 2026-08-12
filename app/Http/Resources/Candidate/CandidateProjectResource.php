<?php

declare(strict_types=1);

namespace App\Http\Resources\Candidate;

use App\Models\CandidateProject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CandidateProject */
class CandidateProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'project_url' => $this->project_url,
            'repository_url' => $this->repository_url,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'technologies' => $this->technologies,
        ];
    }
}
