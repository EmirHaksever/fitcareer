<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidate;

use App\Enums\WorkPreference;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends ApiFormRequest
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
            'headline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'open_to_work' => ['sometimes', 'boolean'],
            'desired_position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'desired_salary_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'desired_salary_max' => ['sometimes', 'nullable', 'numeric', 'min:0', 'gte:desired_salary_min'],
            'work_preference' => ['sometimes', 'nullable', Rule::enum(WorkPreference::class)],
            'years_of_experience' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:80'],
            'linkedin_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'github_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'portfolio_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'user_id' => ['prohibited'],
            'profile_strength_score' => ['prohibited'],
            'cv_parsed_data' => ['prohibited'],
            'cv_file_path' => ['prohibited'],
            'profile_photo_path' => ['prohibited'],
        ];
    }
}
