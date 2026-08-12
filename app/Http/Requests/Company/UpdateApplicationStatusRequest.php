<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use App\Enums\ApplicationStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicationStatusRequest extends ApiFormRequest
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
            'status' => ['required', Rule::enum(ApplicationStatus::class)],
            'note' => ['nullable', 'string', 'max:5000'],
            'candidate_profile_id' => ['prohibited'],
            'job_id' => ['prohibited'],
            'match_score' => ['prohibited'],
            'trust_score' => ['prohibited'],
            'cover_letter' => ['prohibited'],
            'resume_snapshot_path' => ['prohibited'],
            'applied_at' => ['prohibited'],
            'status_updated_at' => ['prohibited'],
            'notes' => ['prohibited'],
        ];
    }
}
