<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use App\Enums\ApplicationStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class ListApplicationsRequest extends ApiFormRequest
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
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'job_id' => ['sometimes', 'integer', 'exists:jobs,id'],
            'status' => ['sometimes', Rule::enum(ApplicationStatus::class)],
        ];
    }
}
