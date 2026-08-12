<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidate;

class UpdateProjectRequest extends StoreProjectRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['title'][0] = 'sometimes';

        return $rules;
    }
}
