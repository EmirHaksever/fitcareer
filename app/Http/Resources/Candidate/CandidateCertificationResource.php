<?php

declare(strict_types=1);

namespace App\Http\Resources\Candidate;

use App\Models\CandidateCertification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CandidateCertification */
class CandidateCertificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'issuing_organization' => $this->issuing_organization,
            'issue_date' => $this->issue_date?->toDateString(),
            'expiration_date' => $this->expiration_date?->toDateString(),
            'credential_id' => $this->credential_id,
            'credential_url' => $this->credential_url,
        ];
    }
}
