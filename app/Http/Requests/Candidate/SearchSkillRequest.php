<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidate;

use App\Http\Requests\ApiFormRequest;

class SearchSkillRequest extends ApiFormRequest
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
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
