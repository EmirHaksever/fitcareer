<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidate;

use App\Enums\EmploymentType;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateExperienceRequest extends ApiFormRequest
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
            'company_name' => ['sometimes', 'string', 'max:255'],
            'position_title' => ['sometimes', 'string', 'max:255'],
            'employment_type' => ['sometimes', 'nullable', Rule::enum(EmploymentType::class)],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_current' => ['sometimes', 'boolean'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'candidate_profile_id' => ['prohibited'],
        ];
    }
}
