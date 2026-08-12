<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidate;

use App\Http\Requests\ApiFormRequest;

class StoreCertificationRequest extends ApiFormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'issuing_organization' => ['required', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'expiration_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'credential_id' => ['nullable', 'string', 'max:255'],
            'credential_url' => ['nullable', 'url', 'max:255'],
            'candidate_profile_id' => ['prohibited'],
        ];
    }
}
