<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidate;

use App\Http\Requests\ApiFormRequest;

class StoreEducationRequest extends ApiFormRequest
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
            'school_name' => ['required', 'string', 'max:255'],
            'degree' => ['nullable', 'string', 'max:255'],
            'field_of_study' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', 'required_unless:is_current,true'],
            'is_current' => ['sometimes', 'boolean'],
            'grade' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:5000'],
            'candidate_profile_id' => ['prohibited'],
        ];
    }
}
