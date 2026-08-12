<?php

declare(strict_types=1);

namespace App\Http\Resources\Candidate;

use App\Models\CandidateProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CandidateProfile */
class CvMetadataResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'has_cv' => $this->cv_file_path !== null,
            'source_filename' => is_array($this->cv_parsed_data)
                ? ($this->cv_parsed_data['source_filename'] ?? null)
                : null,
            'cv_parsed_data' => $this->cv_parsed_data,
        ];
    }
}
