<?php

declare(strict_types=1);

namespace App\Http\Requests\Job;

use App\DTOs\JobSearchQuery;
use App\Enums\EmploymentType;
use App\Enums\ExperienceLevel;
use App\Enums\UserRole;
use App\Enums\WorkType;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class JobSearchRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'employment_type' => ['sometimes', 'nullable', Rule::enum(EmploymentType::class)],
            'work_type' => ['sometimes', 'nullable', Rule::enum(WorkType::class)],
            'experience_level' => ['sometimes', 'nullable', Rule::enum(ExperienceLevel::class)],
            'min_salary' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'max_salary' => ['sometimes', 'nullable', 'numeric', 'min:0', 'gte:min_salary'],
            'min_trust_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'min_fit_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'sort' => ['sometimes', 'nullable', 'string', Rule::in(['published_at', 'salary', 'trust_score', 'fit_score'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'candidate_profile_id' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('min_fit_score')) {
                return;
            }

            $user = $this->user();

            if (
                $user === null
                || $user->role !== UserRole::Candidate
                || $user->candidateProfile === null
            ) {
                $validator->errors()->add(
                    'min_fit_score',
                    'min_fit_score yalnızca kayıtlı adaylar için kullanılabilir.',
                );
            }
        });
    }

    public function toQuery(): JobSearchQuery
    {
        $query = JobSearchQuery::fromValidatedInput($this->validated());
        $user = $this->user();
        $candidateProfileId = null;

        if ($user !== null && $user->role === UserRole::Candidate) {
            $candidateProfileId = $user->candidateProfile?->id;
        }

        return $query->withCandidateProfileId($candidateProfileId);
    }
}
