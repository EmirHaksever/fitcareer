<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidate;

class UpdateCertificationRequest extends StoreCertificationRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['name'][0] = 'sometimes';
        $rules['issuing_organization'][0] = 'sometimes';

        return $rules;
    }
}
