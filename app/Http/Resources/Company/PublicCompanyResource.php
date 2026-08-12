<?php

declare(strict_types=1);

namespace App\Http\Resources\Company;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Company */
class PublicCompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_path' => $this->logo_path,
            'website' => $this->website,
            'industry' => $this->industry,
            'company_size' => $this->company_size?->value,
            'founded_year' => $this->founded_year,
            'description' => $this->description,
            'city' => $this->city,
            'country' => $this->country,
            'is_verified' => $this->is_verified,
            'verification_status' => $this->verification_status->value,
            'trust_score' => $this->trust_score,
            'social_links' => $this->social_links,
        ];
    }
}
