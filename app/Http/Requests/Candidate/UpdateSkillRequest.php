<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidate;

use App\Enums\ProficiencyLevel;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateSkillRequest extends ApiFormRequest
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
            'proficiency_level' => ['sometimes', 'nullable', Rule::enum(ProficiencyLevel::class)],
            'years_of_experience' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:80'],
            'skill_id' => ['prohibited'],
            'candidate_profile_id' => ['prohibited'],
        ];
    }
}
