<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidate;

use App\Http\Requests\ApiFormRequest;

class UploadProfilePhotoRequest extends ApiFormRequest
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
        $maxKb = (int) config('candidate.photo.max_size_kb');

        return [
            'photo' => [
                'required',
                'file',
                'max:'.$maxKb,
                'mimes:'.implode(',', config('candidate.photo.allowed_mimes')),
            ],
        ];
    }
}
