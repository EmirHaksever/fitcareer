<?php

declare(strict_types=1);

namespace App\Http\Requests\Job;

use App\Enums\SkillImportance;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class SyncJobSkillsRequest extends ApiFormRequest
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
            'skills' => ['required', 'array'],
            'skills.*.skill_id' => ['required', 'integer', 'exists:skills,id'],
            'skills.*.importance' => ['required', Rule::enum(SkillImportance::class)],
            'job_id' => ['prohibited'],
            'company_id' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $skills = $this->input('skills', []);

            if (! is_array($skills)) {
                return;
            }

            $skillIds = collect($skills)
                ->pluck('skill_id')
                ->filter(static fn ($skillId): bool => $skillId !== null && $skillId !== '');

            if ($skillIds->count() !== $skillIds->unique()->count()) {
                $validator->errors()->add('skills', 'Duplicate skill_id values are not allowed.');
            }
        });
    }
}
