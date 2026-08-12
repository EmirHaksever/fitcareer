<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidate;

use App\Enums\ProficiencyLevel;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class AttachSkillRequest extends ApiFormRequest
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
            'skill_id' => ['required', 'integer', 'exists:skills,id'],
            'proficiency_level' => ['nullable', Rule::enum(ProficiencyLevel::class)],
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'candidate_profile_id' => ['prohibited'],
        ];
    }
}
