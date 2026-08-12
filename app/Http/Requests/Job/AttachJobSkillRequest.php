<?php

declare(strict_types=1);

namespace App\Http\Requests\Job;

use App\Enums\SkillImportance;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class AttachJobSkillRequest extends ApiFormRequest
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
            'importance' => ['required', Rule::enum(SkillImportance::class)],
            'job_id' => ['prohibited'],
            'company_id' => ['prohibited'],
        ];
    }
}
