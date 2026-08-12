<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use App\Enums\CompanySize;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyProfileRequest extends ApiFormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'industry' => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_size' => ['sometimes', 'nullable', Rule::enum(CompanySize::class)],
            'founded_year' => ['sometimes', 'nullable', 'integer', 'min:1800', 'max:'.date('Y')],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'social_links' => ['sometimes', 'nullable', 'array'],
            'social_links.*' => ['url'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'user_id' => ['prohibited'],
            'slug' => ['prohibited'],
            'logo_path' => ['prohibited'],
            'is_verified' => ['prohibited'],
            'verification_status' => ['prohibited'],
            'trust_score' => ['prohibited'],
        ];
    }
}
