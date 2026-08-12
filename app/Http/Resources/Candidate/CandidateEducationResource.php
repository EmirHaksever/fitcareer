<?php

declare(strict_types=1);

namespace App\Http\Resources\Candidate;

use App\Models\CandidateEducation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CandidateEducation */
class CandidateEducationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_name' => $this->school_name,
            'degree' => $this->degree,
            'field_of_study' => $this->field_of_study,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_current' => $this->is_current,
            'grade' => $this->grade,
            'description' => $this->description,
        ];
    }
}
