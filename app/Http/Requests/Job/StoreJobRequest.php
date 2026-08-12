<?php

declare(strict_types=1);

namespace App\Http\Requests\Job;

use App\Enums\EmploymentType;
use App\Enums\ExperienceLevel;
use App\Enums\WorkType;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreJobRequest extends ApiFormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:50000'],
            'requirements' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'responsibilities' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'work_type' => ['required', Rule::enum(WorkType::class)],
            'experience_level' => ['sometimes', 'nullable', Rule::enum(ExperienceLevel::class)],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'salary_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'salary_max' => ['sometimes', 'nullable', 'numeric', 'min:0', 'gte:salary_min'],
            'salary_currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'is_salary_visible' => ['sometimes', 'boolean'],
            'application_deadline' => ['sometimes', 'nullable', 'date', 'after:today'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'company_id' => ['prohibited'],
            'job_source_id' => ['prohibited'],
            'posted_by' => ['prohibited'],
            'source' => ['prohibited'],
            'status' => ['prohibited'],
            'trust_score' => ['prohibited'],
            'trust_label' => ['prohibited'],
            'trust_analysis_status' => ['prohibited'],
            'slug' => ['prohibited'],
        ];
    }
}
