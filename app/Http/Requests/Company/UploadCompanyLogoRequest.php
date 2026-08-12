<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use App\Http\Requests\ApiFormRequest;

class UploadCompanyLogoRequest extends ApiFormRequest
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
        $maxKb = (int) config('company.logo.max_size_kb');

        return [
            'logo' => [
                'required',
                'file',
                'max:'.$maxKb,
                'mimes:'.implode(',', config('company.logo.allowed_mimes')),
            ],
        ];
    }
}
