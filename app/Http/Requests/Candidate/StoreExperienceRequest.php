<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidate;

use App\Enums\EmploymentType;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreExperienceRequest extends ApiFormRequest
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
            'company_name' => ['required', 'string', 'max:255'],
            'position_title' => ['required', 'string', 'max:255'],
            'employment_type' => ['nullable', Rule::enum(EmploymentType::class)],
            'location' => ['nullable', 'string', 'max:255'],
            'is_current' => ['sometimes', 'boolean'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', 'required_unless:is_current,true'],
            'description' => ['nullable', 'string', 'max:5000'],
            'candidate_profile_id' => ['prohibited'],
        ];
    }
}
