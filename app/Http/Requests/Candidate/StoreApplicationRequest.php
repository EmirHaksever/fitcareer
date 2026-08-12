<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidate;

use App\Http\Requests\ApiFormRequest;

class StoreApplicationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'job_id' => ['required', 'integer', 'exists:jobs,id'],
            'cover_letter' => ['nullable', 'string', 'max:5000'],
            'candidate_profile_id' => ['prohibited'],
            'status' => ['prohibited'],
            'match_score' => ['prohibited'],
            'trust_score' => ['prohibited'],
            'resume_snapshot_path' => ['prohibited'],
            'applied_at' => ['prohibited'],
            'status_updated_at' => ['prohibited'],
            'notes' => ['prohibited'],
        ];
    }
}
